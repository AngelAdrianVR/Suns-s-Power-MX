<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchTrait; // Trait Multi-sucursal

class Payment extends Model
{
    use BelongsToBranchTrait;

    protected $fillable = [
        'client_id',
        'service_order_id',
        'amount',
        'payment_date',
        'method', // Enum: Efectivo, Transferencia...
        'reference',
        'notes',
        'branch_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}