<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WasteType extends Model
{
    protected $fillable = ['name', 'unit', 'current_buy_price', 'is_active'];

    protected function casts(): array
    {
        return ['current_buy_price' => 'integer', 'is_active' => 'boolean'];
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(WastePriceHistory::class);
    }

    public function depositItems(): HasMany
    {
        return $this->hasMany(DepositItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockKg(): string
    {
        return (string) $this->stockMovements()->sum('weight_signed');
    }
}
