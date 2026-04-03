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

    public function destroy($systemTypeId, $productId)
    {
        $systemType = SystemType::findOrFail($systemTypeId);
        
        $systemType->products()->detach($productId);

        return back()->with('success', 'Producto removido del tipo de sistema.');
    }
}