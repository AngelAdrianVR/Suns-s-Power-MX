<?php

namespace App\Http\Controllers;

use App\Models\EvidenceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EvidenceTemplateController extends Controller
{
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'system_type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'allows_multiple' => 'boolean' // Validar el nuevo campo
        ]);

        // Calcular el siguiente número de orden para el nuevo registro
        $maxOrder = EvidenceTemplate::where('branch_id', $branchId)
            ->where('system_type', $validated['system_type'])
            ->max('order') ?? 0;

        EvidenceTemplate::create([
            'branch_id' => $branchId,
            'system_type' => $validated['system_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'allows_multiple' => $validated['allows_multiple'] ?? false,
            'order' => $maxOrder + 1, // Asignar al final de la lista
        ]);

        return back()->with('success', 'Plantilla de evidencia agregada correctamente.');
    }

    public function update(Request $request, EvidenceTemplate $evidenceTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($evidenceTemplate->branch_id !== $branchId) abort(403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'allows_multiple' => 'boolean' // Validar el nuevo campo
        ]);

        $evidenceTemplate->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'allows_multiple' => $validated['allows_multiple'] ?? false,
        ]);

        return back()->with('success', 'Plantilla de evidencia actualizada.');
    }

    public function destroy(EvidenceTemplate $evidenceTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($evidenceTemplate->branch_id !== $branchId) abort(403);

        $evidenceTemplate->delete();

        return back()->with('success', 'Plantilla de evidencia eliminada.');
    }

    // NUEVO MÉTODO: Guarda el orden cuando el usuario arrastra las tarjetas
    public function reorder(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:evidence_templates,id',
            'items.*.order' => 'required|integer'
        ]);

        foreach ($validated['items'] as $item) {
            EvidenceTemplate::where('id', $item['id'])
                ->where('branch_id', $branchId)
                ->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Orden actualizado.');
    }
}