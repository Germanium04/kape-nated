<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = ['key', 'name', 'unit', 'stock', 'reorder_level', 'cost'];

    protected $casts = [
        'stock'         => 'float',
        'reorder_level' => 'float',
        'cost'          => 'float',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLow(): bool
    {
        return $this->stock <= $this->reorder_level;
    }

    /** 'out' | 'low' | 'ok', used by the badges on the inventory and dashboard pages. */
    public function getStateAttribute(): string
    {
        if ($this->stock <= 0) return 'out';

        return $this->isLow() ? 'low' : 'ok';
    }
}