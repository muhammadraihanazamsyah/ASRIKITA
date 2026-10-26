<?php

namespace App\Http\Controllers;

use App\Http\Requests\MasterDataRequest;
use App\Services\MasterDataService;
use App\Support\MasterData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $definition = MasterData::definition($resource);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $definition['model']::query();
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($query) use ($definition, $pattern): void {
                foreach ($definition['search'] as $column) {
                    $query->orWhere($column, 'ilike', $pattern);
                }
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where($definition['status'], $resource === 'nasabah' ? $status : $status === 'active');
        }
        $records = $query->orderBy($definition['name'])->orderBy('id')->paginate(15)->withQueryString();

        return view('master.index', compact('definition', 'resource', 'records'));
    }

    public function create(string $resource): View
    {
        $definition = MasterData::definition($resource);
        $record = new $definition['model'];
        $history = null;

        return view('master.form', compact('definition', 'resource', 'record', 'history'));
    }

    public function store(MasterDataRequest $request, string $resource, MasterDataService $service): RedirectResponse
    {
        $record = $service->save($resource, $request->validated(), $request->user());

        return redirect()->route('admin.master.edit', [$resource, $record->id])->with('status', 'Data berhasil ditambahkan.');
    }

    public function edit(string $resource, int $record): View
    {
        $definition = MasterData::definition($resource);
        $record = $definition['model']::findOrFail($record);
        $history = $resource === 'jenis-sampah' ? $record->priceHistories()->with('changedBy')->orderByDesc('changed_at')->orderByDesc('id')->paginate(10) : null;

        return view('master.form', compact('definition', 'resource', 'record', 'history'));
    }

    public function update(MasterDataRequest $request, string $resource, int $record, MasterDataService $service): RedirectResponse
    {
        $service->save($resource, $request->validated(), $request->user(), $record);

        return redirect()->route('admin.master.edit', [$resource, $record])->with('status', 'Perubahan berhasil disimpan.');
    }

    public function deactivate(Request $request, string $resource, int $record, MasterDataService $service): RedirectResponse
    {
        $service->deactivate($resource, $record, $request->user());

        return redirect()->route('admin.master.index', $resource)->with('status', 'Data dinonaktifkan. Riwayat tetap tersimpan; aktifkan kembali melalui halaman ubah.');
    }
}
