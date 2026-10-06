<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    // 'change' is negative for usage (reason 'order') and positive for 'restock'.
    protected $fillable = ['ingredient_id', 'order_id', 'change', 'reason'];

    protected $casts = ['change' => 'float'];

    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function order()      { return $this->belongsTo(Order::class); }
}