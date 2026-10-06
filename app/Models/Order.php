<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['order_no', 'user_id', 'branch_id', 'payment_method', 'status'];

    public function items()  { return $this->hasMany(OrderItem::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function staff()  { return $this->belongsTo(User::class, 'user_id'); }

    /** The orders table has no total column, so it is worked out from the lines. Load items.addons first. */
    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn (OrderItem $i) => $i->line_total);
    }
}