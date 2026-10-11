<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'name', 
        'price', 
        'price_venti', 
        'has_sizes', 
        'has_temperature', 
        'drink_type_id', 
        'is_active', 
        'image_path'
    ];

    protected $casts = [
        'price'           => 'float',
        'price_venti'     => 'float',
        'has_sizes'       => 'boolean',
        'has_temperature' => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function drinkType()
    {
        return $this->belongsTo(DrinkType::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'menu_item_ingredient')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}