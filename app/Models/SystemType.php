<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBranchTrait; // Si usas este trait para el scope automático de sucursales

class SystemType extends Model
{
    use HasFactory;
    
    // Si tienes un trait para filtrar automáticamente por sucursal, puedes usarlo:
    // use BelongsToBranchTrait;

    protected $fillable = [
        'branch_id',
        'name',
    ];

    /**
     * Relación con la sucursal (Branch)
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_system_type')
                    ->withPivot('quantity', 'order') // Agregar order aquí
                    ->withTimestamps()
                    ->orderByPivot('order', 'asc');  // Hace que se ordenen automáticamente
    }
}