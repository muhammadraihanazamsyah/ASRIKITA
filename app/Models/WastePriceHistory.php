<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastePriceHistory extends Model
{
    protected $table = 'waste_price_history';

    public $timestamps = false;

    protected $fillable = ['waste_type_id', 'old_buy_price', 'new_buy_price', 'changed_by', 'changed_at', 'note'];

    protected function casts(): array
    {
        return ['old_buy_price' => 'integer', 'new_buy_price' => 'integer', 'changed_at' => 'immutable_datetime'];
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
