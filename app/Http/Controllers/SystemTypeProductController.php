<?php

namespace App\Http\Controllers;

use App\Models\SystemType;
use Illuminate\Http\Request;

class SystemTypeProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'system_type_id' => 'required|exists:system_types,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $systemType = SystemType::findOrFail($validated['system_type_id']);
        
        // syncWithoutDetaching para agregar o actualizar sin borrar los demás
        $systemType->products()->syncWithoutDetaching([
            $validated['product_id'] => ['quantity' => $validated['quantity']]
        ]);

        return back()->with('success', 'Producto asignado correctamente.');
    }

    // NUEVO: Método para actualizar la cantidad
    public function update(Request $request, $systemTypeId, $productId)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $systemType = SystemType::findOrFail($systemTypeId);
        
        // updateExistingPivot actualiza solo los datos pivote de la relación específica
        $systemType->products()->updateExistingPivot($productId, [
            'quantity' => $validated['quantity']
        ]);

        return back()->with('success', 'Cantidad de material actualizada.');
    }

    public function destroy($systemTypeId, $productId)
    {
        $systemType = SystemType::findOrFail($systemTypeId);
        
        $systemType->products()->detach($productId);

        return back()->with('success', 'Producto removido del tipo de sistema.');
    }

    // NUEVO: Método para reordenar los productos con Drag & Drop
    public function reorder(Request $request, $systemTypeId)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products,id',
            'items.*.order' => 'required|integer'
        ]);

        $systemType = \App\Models\SystemType::findOrFail($systemTypeId);

        foreach ($validated['items'] as $item) {
            $systemType->products()->updateExistingPivot($item['id'], [
                'order' => $item['order']
            ]);
        }
        
        return back()->with('success', 'Orden de materiales actualizado.');
    }
}