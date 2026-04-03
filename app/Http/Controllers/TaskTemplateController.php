<?php

namespace App\Http\Controllers;

use App\Models\TaskTemplate;
use App\Models\EvidenceTemplate;
use App\Models\User;
use App\Models\SystemType; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaskTemplateController extends Controller
{
    public function index()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $taskTemplates = TaskTemplate::with('users:id,name,profile_photo_path')
            ->where('branch_id', $branchId)
            ->get();

        $evidenceTemplates = EvidenceTemplate::where('branch_id', $branchId)->get();

        $systemTypes = SystemType::where('branch_id', $branchId)->get(['id', 'name']);

        if ($systemTypes->isEmpty()) {
             $defaultTypes = ['Interconectado', 'Autónomo', 'Multimodo', 'Respaldo', 'Bombeo'];
             foreach($defaultTypes as $type){
                 SystemType::create(['branch_id' => $branchId, 'name' => $type]);
             }
             $systemTypes = SystemType::where('branch_id', $branchId)->get(['id', 'name']);
        }

        $assignableUsers = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('id', '!=', 1)
            ->select('id', 'name', 'profile_photo_path')
            ->orderBy('name')
            ->get();

        return Inertia::render('Setting/TaskTemplates/Index', [
            'task_templates' => $taskTemplates,
            'evidence_templates' => $evidenceTemplates,
            'system_types' => $systemTypes,
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
            'start_days' => 'required|integer|min:0', // <-- NUEVO
            'duration_days' => 'required|integer|min:1', // <-- NUEVO
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ]);

        $template = TaskTemplate::create([
            'branch_id' => $branchId,
            'system_type' => $validated['system_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'start_days' => $validated['start_days'], // <-- NUEVO
            'duration_days' => $validated['duration_days'], // <-- NUEVO
        ]);

        if (!empty($validated['users'])) {
            $template->users()->sync($validated['users']);
        }

        return back()->with('success', 'Plantilla de tarea creada correctamente.');
    }

    public function update(Request $request, TaskTemplate $taskTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($taskTemplate->branch_id !== $branchId) abort(403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Baja,Media,Alta',
            'start_days' => 'required|integer|min:0', // <-- NUEVO
            'duration_days' => 'required|integer|min:1', // <-- NUEVO
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ]);

        $taskTemplate->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'start_days' => $validated['start_days'], // <-- NUEVO
            'duration_days' => $validated['duration_days'], // <-- NUEVO
        ]);

        if (isset($validated['users'])) {
            $taskTemplate->users()->sync($validated['users']);
        } else {
            $taskTemplate->users()->detach();
        }

        return back()->with('success', 'Plantilla actualizada correctamente.');
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($taskTemplate->branch_id !== $branchId) abort(403);
        $taskTemplate->delete();
        return back()->with('success', 'Plantilla eliminada.');
    }
}