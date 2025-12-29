<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'branch_id', // campo para multi-tenancy
        'company_name',
        'contact_name',
        'email',
        'phone',
    ];

    /**
     * Relación: Un proveedor pertenece a una sucursal.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
                    ->withPivot('supplier_sku', 'purchase_price', 'currency', 'delivery_days')
                    ->withTimestamps();
    }
}