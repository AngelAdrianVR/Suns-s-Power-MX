<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    protected $fillable = [
        'branch_id', // campo para multi-tenancy
        'company_name',
        'website', // Nuevo campo agregado
        
        // // Mantenemos estos por compatibilidad temporal, pero 
        // // idealmente se deberían usar la relación 'contacts'
        // 'contact_name',
        // 'email',
        // 'phone',
    ];

    /**
     * Relación: Un proveedor pertenece a una sucursal.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relación Polimórfica: Un proveedor puede tener múltiples contactos.
     * Esto reemplaza el uso de campos estáticos para permitir N contactos.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    /**
     * Helper para obtener el contacto principal rápidamente.
     */
    public function mainContact()
    {
        return $this->morphOne(Contact::class, 'contactable')->where('is_primary', true)->latest();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
                    ->withPivot('supplier_sku', 'purchase_price', 'currency', 'delivery_days')
                    ->withTimestamps();
    }
}