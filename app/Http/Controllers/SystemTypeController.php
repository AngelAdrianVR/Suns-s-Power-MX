<?php

namespace App\Http\Controllers;

use App\Models\SystemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemTypeController extends Controller
{
    /**
     * Almacena un nuevo Tipo de Sistema.
     */
    public function store(Request $request)
    {
        // Validar el permiso específico para crear
        abort_if(!Auth::user()->can('system_type.create'), 403, 'No tienes permiso para crear tipos de sistema.');

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Evitar duplicados en la misma sucursal (opcional pero recomendado)
        $exists = SystemType::where('branch_id', $branchId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Este tipo de sistema ya existe.'])->withInput();
        }

        SystemType::create([
            'branch_id' => $branchId,
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Tipo de sistema creado correctamente.');
    }

    /**
     * Actualiza el nombre de un Tipo de Sistema.
     */
    public function update(Request $request, SystemType $systemType)
    {
        // Validar el permiso específico para editar
        abort_if(!Auth::user()->can('system_type.edit'), 403, 'No tienes permiso para editar tipos de sistema.');

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        // Validar que el tipo de sistema pertenezca a la sucursal actual
        if ($systemType->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para editar este tipo de sistema.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Verificar duplicados al actualizar, ignorando el actual
        $exists = SystemType::where('branch_id', $branchId)
            ->where('name', $validated['name'])
            ->where('id', '!=', $systemType->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Este nombre ya está en uso.'])->withInput();
        }

        $systemType->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Tipo de sistema actualizado.');
    }

    /**
     * Elimina un Tipo de Sistema.
     */
    public function destroy(SystemType $systemType)
    {
        // Validar el permiso específico para eliminar
        abort_if(!Auth::user()->can('system_type.delete'), 403, 'No tienes permiso para eliminar tipos de sistema.');

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        // Validar pertenencia
        if ($systemType->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para eliminar este tipo de sistema.');
        }

        $systemType->delete();

        return back()->with('success', 'Tipo de sistema eliminado correctamente.');
    }
}