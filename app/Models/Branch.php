<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'branch_inventory')
            ->withPivot(['stock', 'reorder_level', 'is_active'])
            ->withTimestamps();
    }
}