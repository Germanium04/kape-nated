<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'menu_item_id', 'quantity', 'unit_price', 'temperature'];

    protected $casts = ['unit_price' => 'float'];

    public function order()    { return $this->belongsTo(Order::class); }
    public function menuItem() { return $this->belongsTo(MenuItem::class); }

    /** Add-ons on this line, with the price they had at order time on the pivot. */
    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'order_item_addon')
            ->withPivot('unit_price')->withTimestamps();
    }

    public function getLineTotalAttribute(): float
    {
        $addons = $this->addons->sum(fn ($a) => (float) $a->pivot->unit_price);

        return ($this->unit_price + $addons) * $this->quantity;
    }
}