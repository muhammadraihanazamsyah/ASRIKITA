<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WastePriceHistory;
use App\Models\WasteType;
use App\Support\MasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    public function save(string $resource, array $data, User $actor, ?int $id = null): Model
    {
        abort_unless($actor->role === 'admin' && $actor->status === 'active', 403);
        $definition = MasterData::definition($resource);

        try {
            return DB::transaction(function () use ($data, $actor, $id, $definition) {
                $record = $id ? $definition['model']::query()->lockForUpdate()->findOrFail($id) : new $definition['model'];
                $before = $record->only($record->getFillable());
                $oldPrice = $record instanceof WasteType && $record->exists ? $record->current_buy_price : null;

                if ($record instanceof WasteType && $record->exists && (string) $oldPrice !== (string) $data['expected_price']) {
                    throw ValidationException::withMessages(['current_buy_price' => 'Harga telah diubah oleh admin lain. Muat ulang halaman sebelum menyimpan.']);
                }

                $record->fill(array_intersect_key($data, array_flip($record->getFillable())));
                if ($record instanceof WasteType) {
                    $record->unit = 'kg';
                }
                $changed = ! $record->exists || $record->isDirty();
                $record->save();

                if ($record instanceof WasteType && ($oldPrice === null || $oldPrice !== $record->current_buy_price)) {
                    WastePriceHistory::create([
                        'waste_type_id' => $record->id, 'old_buy_price' => $oldPrice,
                        'new_buy_price' => $record->current_buy_price, 'changed_by' => $actor->id,
                        'changed_at' => now(), 'note' => $data['note'] ?? null,
                    ]);
                }

                if ($changed) {
                    $this->audit($record, $actor, $id ? 'master.updated' : 'master.created', $before);
                }

                return $record;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A competing request may pass validation before another insert commits.
            throw ValidationException::withMessages([
                $resource === 'nasabah' ? 'member_code' : 'name' => 'Data dengan kode atau nama ini sudah digunakan.',
            ]);
        }
    }

    public function deactivate(string $resource, int $id, User $actor): void
    {
        abort_unless($actor->role === 'admin' && $actor->status === 'active', 403);
        $definition = MasterData::definition($resource);
        DB::transaction(function () use ($resource, $id, $actor, $definition): void {
            $record = $definition['model']::query()->lockForUpdate()->findOrFail($id);
            $before = $record->only($record->getFillable());
            $record->{$definition['status']} = $resource === 'nasabah' ? 'inactive' : false;
            if ($record->isDirty()) {
                $record->save();
                $this->audit($record, $actor, 'master.deactivated', $before);
            }
        });
    }

    private function audit(Model $record, User $actor, string $action, array $before): void
    {
        $audit = new AuditLog;
        $audit->forceFill([
            'actor_id' => $actor->id, 'action' => $action, 'entity_type' => $record->getTable(),
            'entity_id' => $record->id,
            'metadata' => ['before' => $before, 'after' => $record->only($record->getFillable())],
        ])->save();
    }
}
