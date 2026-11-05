<?php

namespace App\Support;

use App\Models\Collector;
use App\Models\Member;
use App\Models\WasteType;

class MasterData
{
    public const RESOURCES = ['nasabah', 'jenis-sampah', 'pengepul'];

    public static function definition(string $resource): array
    {
        return match ($resource) {
            'nasabah' => ['model' => Member::class, 'title' => 'Nasabah', 'name' => 'full_name', 'status' => 'status', 'search' => ['member_code', 'full_name', 'phone']],
            'jenis-sampah' => ['model' => WasteType::class, 'title' => 'Jenis sampah & harga', 'name' => 'name', 'status' => 'is_active', 'search' => ['name']],
            'pengepul' => ['model' => Collector::class, 'title' => 'Pengepul', 'name' => 'name', 'status' => 'is_active', 'search' => ['name', 'phone']],
            default => abort(404),
        };
    }
}
