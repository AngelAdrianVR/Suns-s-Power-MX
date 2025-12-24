<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'purchase_price',
        'sale_price',
        'image_path', // Mantenemos este por si usas carga simple, o puedes usar Spatie Media Collection
    ];

    // --- Relaciones ---

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relación con Sucursales para manejo de Stock
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
                    ->withPivot(['current_stock', 'min_stock_alert', 'location_in_warehouse'])
                    ->withTimestamps();
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
                    ->withPivot('supplier_sku', 'purchase_price', 'currency', 'delivery_days', 'is_preferred')
                    ->withTimestamps();
    }

    // Helper para obtener el mejor precio disponible
    public function getBestSupplierAttribute()
    {
        return $this->suppliers()->orderByPivot('purchase_price', 'asc')->first();
    }

    // Definir colección de imágenes para Spatie (Opcional)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_images')
             ->useDisk('public')
             ->singleFile(); // O multi-file si quieres galería
    }
}
