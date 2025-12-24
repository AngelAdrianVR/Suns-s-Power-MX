<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    /**
     * Incrementa el stock (Compras, Devoluciones, Inventario Inicial).
     */
    public static function addStock(Product $product, int|float $branchId, float $quantity, string $reason, $reference = null, ?string $notes = null)
    {
        return self::recordMovement($product, $branchId, $quantity, 'Entrada', $reason, $reference, $notes);
    }

    /**
     * Decrementa el stock (Ventas, Mermas, Instalaciones).
     */
    public static function removeStock(Product $product, int|float $branchId, float $quantity, string $reason, $reference = null, ?string $notes = null)
    {
        return self::recordMovement($product, $branchId, $quantity, 'Salida', $reason, $reference, $notes);
    }

    /**
     * Ajuste directo (Corrección de inventario físico).
     * Calcula la diferencia y asegura que el stock final sea EXPLICITAMENTE el solicitado.
     */
    public static function adjustStock(Product $product, int|float $branchId, float $newRealStock, ?string $notes = null)
    {
        $currentStock = $product->branches()->where('branch_id', $branchId)->first()?->pivot->current_stock ?? 0;
        
        if ($newRealStock == $currentStock) return;

        $diff = $newRealStock - $currentStock;
        
        // Si la diferencia es positiva, es una entrada (recuperamos stock). Si negativa, salida.
        // Pero mantenemos el tipo 'Ajuste' para que en el historial se vea como Ajuste.
        // Pasamos $newRealStock como último parámetro para forzar el valor final.
        return self::recordMovement(
            product: $product, 
            branchId: $branchId, 
            quantity: abs($diff), 
            type: 'Ajuste', 
            reason: 'correction', 
            reference: null, 
            notes: $notes,
            overrideStock: $newRealStock // <--- CLAVE: Forzamos el valor final
        );
    }

    /**
     * Lógica centralizada de movimientos.
     * @param float|null $overrideStock Si se define, ignora la suma/resta y establece este valor exacto.
     */
    private static function recordMovement(Product $product, int $branchId, float $quantity, string $type, string $reason, $reference = null, ?string $notes = null, ?float $overrideStock = null)
    {
        return DB::transaction(function () use ($product, $branchId, $quantity, $type, $reference, $notes, $overrideStock) {
            
            // 1. Obtener relación en pivote con bloqueo para evitar condiciones de carrera
            $pivot = DB::table('branch_product')
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate() 
                ->first();

            $currentStock = $pivot ? $pivot->current_stock : 0;
            
            // 2. Calcular nuevo stock
            if (!is_null($overrideStock)) {
                $newStock = $overrideStock;
            } else {
                // Lógica estándar para Entrada/Salida
                $newStock = match ($type) {
                    'Entrada' => $currentStock + $quantity,
                    'Salida' => $currentStock - $quantity,
                    // Fallback por seguridad si llega Ajuste sin override (asumimos suma si no se especifica, pero adjustStock siempre debe mandar override)
                    'Ajuste' => $currentStock + $quantity, 
                    default => $currentStock
                };
            }

            // Validación de seguridad para no guardar negativos si la DB es unsigned
            if ($newStock < 0) {
                 // Opción A: Lanzar error
                 // throw new \Exception("El stock no puede ser negativo.");
                 // Opción B: Forzar a 0
                 $newStock = 0;
            }

            // 3. Actualizar o Crear en Pivote
            if ($pivot) {
                DB::table('branch_product')
                    ->where('id', $pivot->id)
                    ->update(['current_stock' => $newStock, 'updated_at' => now()]);
            } else {
                DB::table('branch_product')->insert([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'current_stock' => $newStock,
                    'min_stock_alert' => 5,
                    'location_in_warehouse' => 'N/A', // Opcional
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Registrar en Historial
            StockMovement::create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => $type,
                'quantity' => $quantity,
                'stock_after' => $newStock,
                'notes' => $notes,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            return $newStock;
        });
    }
}