<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\Branch;

/**
 * Trait BelongsToBranch
 * * Este trait aplica automáticamente el filtro por sucursal a cualquier modelo.
 * Lógica:
 * 1. Scope Global: Filtra las consultas (SELECT) para mostrar solo datos de la sucursal del usuario.
 * 2. Observer Creating: Asigna automáticamente el branch_id al crear nuevos registros.
 */
trait BelongsToBranch
{
    /**
     * El método "booted" se ejecuta automáticamente cuando el modelo se inicializa.
     */
    protected static function bootBelongsToBranch(): void
    {
        // 1. APLICAR FILTRO AUTOMÁTICO (GLOBAL SCOPE)
        static::addGlobalScope('branch', function (Builder $builder) {
            
            // Si corremos desde consola (artisan) o no hay usuario logueado, no filtramos.
            // Esto es útil para seeders o tareas programadas.
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            // Lógica de Filtrado:
            // Si el usuario tiene una sucursal asignada (branch_id != null),
            // filtramos todos los queries para esa sucursal.
            if ($user->branch_id) {
                $builder->where('branch_id', $user->branch_id);
            }
            
            // Nota: Si branch_id es null (Super Admin), no aplicamos where,
            // por lo que verá todos los registros de todas las sucursales.
        });

        // 2. ASIGNACIÓN AUTOMÁTICA AL CREAR (CREATING EVENT)
        static::creating(function ($model) {
            // Si hay un usuario logueado con sucursal, y el modelo no tiene branch_id asignado aún...
            if (Auth::check() && Auth::user()->branch_id && !$model->branch_id) {
                // ...asignamos la sucursal del usuario automáticamente.
                $model->branch_id = Auth::user()->branch_id;
            }
        });
    }

    /**
     * Definir la relación "belongsTo Branch" para que esté disponible
     * en cualquier modelo que use este trait.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}