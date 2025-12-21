<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderItem extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'service_order_id',
        'product_id',
        'quantity',
        'price', // Precio histórico al momento de la venta
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    // Calculado: Total de la línea
    public function getTotalAttribute()
    {
        return $this->quantity * $this->price;
    }
}