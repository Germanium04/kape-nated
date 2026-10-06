<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['name', 'price', 'drink_type_id', 'is_active'];

    protected $casts = ['price' => 'float', 'is_active' => 'boolean'];

    public function drinkType() { return $this->belongsTo(DrinkType::class); }

    /** Recipe: what ONE serving uses. Pivot table is menu_item_ingredient (singular). */
    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'menu_item_ingredient')
            ->withPivot('quantity')->withTimestamps();
    }
}