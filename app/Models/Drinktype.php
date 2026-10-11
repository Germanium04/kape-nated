<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrinkType extends Model
{
    protected $fillable = ['name'];

    public function menuItems() 
    { 
        return $this->hasMany(MenuItem::class); 
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'addon_drink_type')
            ->withTimestamps();
    }
}