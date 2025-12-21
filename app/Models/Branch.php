<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con Usuarios asignados a esta sucursal
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relación con Productos (Inventario)
     * Accedemos a la tabla pivote para saber el stock en esta sucursal.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
                    ->withPivot(['current_stock', 'min_stock_alert', 'location_in_warehouse'])
                    ->withTimestamps();
    }

    // Relaciones con entidades transaccionales
    public function clients(): HasMany { return $this->hasMany(Client::class); }
    public function serviceOrders(): HasMany { return $this->hasMany(ServiceOrder::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}