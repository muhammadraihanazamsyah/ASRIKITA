<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collector extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
