<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'key', 'name', 'unit', 'purchase_unit', 
        'conversion_factor', 'cost', 'is_active'
    ];

    protected $casts = [
        'cost'              => 'float',
        'conversion_factor' => 'float',
        'is_active'         => 'boolean',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_inventory')
            ->withPivot(['stock', 'reorder_level', 'is_active'])
            ->withTimestamps();
    }

    public function isLow(?float $customStock = null, ?float $customReorder = null): bool
    {
        $stock = $customStock ?? (isset($this->stock) ? (float) $this->stock : 0);
        $reorder = $customReorder ?? (isset($this->reorder_level) ? (float) $this->reorder_level : 0);

        return $stock <= $reorder;
    }
}