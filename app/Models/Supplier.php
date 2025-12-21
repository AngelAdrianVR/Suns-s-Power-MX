<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    // Directorio global de proveedores
    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}