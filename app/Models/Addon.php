<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = ['name', 'price'];

    protected $casts = ['price' => 'float'];

    /** Pivot table is addon_ingredient (singular). */
    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'addon_ingredient')
            ->withPivot('quantity')->withTimestamps();
    }
}