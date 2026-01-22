<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Supplier extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'branch_id', 
        'company_name',
        'website',
        
        // Campos fiscales y bancarios
        'rfc',
        'address',
        'bank_account_holder',
        'bank_name',
        'clabe',
        'account_number',
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