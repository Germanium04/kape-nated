<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'ingredient_id', 
        'order_id', 
        'user_id', 
        'change', 
        'reason'
    ];

    protected $casts = [
        'change' => 'float'
    ];

    public function ingredient() 
    { 
        return $this->belongsTo(Ingredient::class); 
    }

    public function order()      
    { 
        return $this->belongsTo(Order::class); 
    }

    public function user()      
    { 
        return $this->belongsTo(User::class); 
    }
}