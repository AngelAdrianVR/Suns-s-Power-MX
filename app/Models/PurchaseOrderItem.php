<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_cost', // Costo histórico
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalAttribute()
    {
        return $this->quantity * $this->unit_cost;
    }
}