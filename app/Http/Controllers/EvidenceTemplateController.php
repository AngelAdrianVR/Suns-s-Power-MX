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
        ]);

        EvidenceTemplate::create([
            'branch_id' => $branchId,
            'system_type' => $validated['system_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
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
        ]);

        $evidenceTemplate->update($validated);

        return back()->with('success', 'Plantilla de evidencia actualizada.');
    }

    public function destroy(EvidenceTemplate $evidenceTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($evidenceTemplate->branch_id !== $branchId) abort(403);

        $evidenceTemplate->delete();

        return back()->with('success', 'Plantilla de evidencia eliminada.');
    }
}