<?php

namespace App\Http\Controllers;

use App\Models\TaskTemplate;
use App\Models\SystemType;
use App\Models\EvidenceTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia; // <-- Añade esta importación arriba si no la tienes

class TaskTemplateController extends Controller
{
    // AÑADE ESTA FUNCIÓN INDEX AQUI
    public function index()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Cargar los tipos de sistema con sus productos asignados (para la pestaña "Material Requerido")
        $systemTypes = SystemType::where('branch_id', $branchId)
            ->with(['products' => function($q) {
                $q->withPivot('quantity');
            }])->get();

        // Cargar plantillas de tareas con sus relaciones necesarias (usuarios y evidencias)
        $taskTemplates = TaskTemplate::where('branch_id', $branchId)
            ->with(['users', 'evidenceTemplates'])
            ->get();

        // Cargar plantillas de evidencias con sus relaciones (tareas que las requieren)
        $evidenceTemplates = EvidenceTemplate::where('branch_id', $branchId)
            ->with('taskTemplates')
            ->orderBy('order', 'asc') // Respetar el orden en el que las arrastran
            ->get();

        // Cargar los usuarios que pueden ser asignables
        $assignableUsers = User::where('branch_id', $branchId)->get();

        // Enviar la información a la vista de Vue (IndexTemplates.vue)
        return Inertia::render('Setting/TaskTemplates/Index', [
            'system_types' => $systemTypes,
            'task_templates' => $taskTemplates,
            'evidence_templates' => $evidenceTemplates,
            'assignable_users' => $assignableUsers,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'system_type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Baja,Media,Alta',
            'start_days' => 'integer|min:0',
            'duration_days' => 'integer|min:1',
            'is_recurring' => 'boolean',
            'recurring_interval' => 'nullable|integer|min:1',
            'recurring_unit' => 'nullable|in:days,weeks,months,years',
            'recurring_count' => 'nullable|integer|min:1', // <-- NUEVO CAMPO AÑADIDO
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
            'evidences' => 'nullable|array',
            'evidences.*' => 'exists:evidence_templates,id',
        ]);

        $taskTemplate = TaskTemplate::create([
            'branch_id' => $branchId,
            'system_type' => $validated['system_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'start_days' => $validated['start_days'] ?? 0,
            'duration_days' => $validated['duration_days'] ?? 1,
            'is_recurring' => $validated['is_recurring'] ?? false,
            'recurring_interval' => $validated['recurring_interval'] ?? 1,
            'recurring_unit' => $validated['recurring_unit'] ?? 'months',
            'recurring_count' => $validated['recurring_count'] ?? 1, // <-- NUEVO CAMPO GUARDADO
        ]);

        if (!empty($validated['users'])) {
            $taskTemplate->users()->sync($validated['users']);
        }

        if (!empty($validated['evidences'])) {
            $taskTemplate->evidenceTemplates()->sync($validated['evidences']);
        }

        return back()->with('success', 'Plantilla de tarea guardada.');
    }

    public function update(Request $request, TaskTemplate $taskTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($taskTemplate->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Baja,Media,Alta',
            'start_days' => 'integer|min:0',
            'duration_days' => 'integer|min:1',
            'is_recurring' => 'boolean',
            'recurring_interval' => 'nullable|integer|min:1',
            'recurring_unit' => 'nullable|in:days,weeks,months,years',
            'recurring_count' => 'nullable|integer|min:1', // <-- ASEGURAMOS QUE ESTÉ AQUÍ TAMBIÉN
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
            'evidences' => 'nullable|array',
            'evidences.*' => 'exists:evidence_templates,id',
        ]);

        $taskTemplate->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'start_days' => $validated['start_days'] ?? 0,
            'duration_days' => $validated['duration_days'] ?? 1,
            'is_recurring' => $validated['is_recurring'] ?? false,
            'recurring_interval' => $validated['recurring_interval'] ?? 1,
            'recurring_unit' => $validated['recurring_unit'] ?? 'months',
            'recurring_count' => $validated['recurring_count'] ?? 1, // <-- GUARDAR AL EDITAR
        ]);

        // Sincronizar usuarios
        $taskTemplate->users()->sync($validated['users'] ?? []);

        // Sincronizar evidencias
        $taskTemplate->evidenceTemplates()->sync($validated['evidences'] ?? []);

        return back()->with('success', 'Plantilla de tarea actualizada.');
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($taskTemplate->branch_id !== $branchId) {
            abort(403);
        }

        $taskTemplate->delete();

        return back()->with('success', 'Plantilla de tarea eliminada.');
    }
}