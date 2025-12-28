<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'service_order_id' => 'required|exists:service_orders,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:Baja,Media,Alta',
            'user_ids' => 'required|array|min:1', // Al menos un asignado
            'user_ids.*' => 'exists:users,id'
        ]);

        DB::transaction(function () use ($validated, $branchId) {
            // 1. Crear Tarea
            $task = Task::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'service_order_id' => $validated['service_order_id'],
                'branch_id' => $branchId,
                'created_by' => Auth::id(),
                'status' => 'Pendiente',
                'priority' => $validated['priority'],
                'start_date' => now(), // Iniciamos tracking o dejamos null según lógica
                'due_date' => $validated['due_date'] ?? null,
            ]);

            // 2. Asignar Usuarios (Tabla Pivote)
            $task->assignees()->sync($validated['user_ids']);

            // 3. Notificar a los usuarios asignados
            $assignees = User::whereIn('id', $validated['user_ids'])->get();
            
            // Excluimos al creador si se auto-asigna para no notificarle a él mismo (opcional)
            $usersToNotify = $assignees->filter(fn($u) => $u->id !== Auth::id());

            if ($usersToNotify->isNotEmpty()) {
                Notification::send($usersToNotify, new TaskAssignedNotification($task, Auth::user()));
            }
        });

        return back()->with('success', 'Tarea creada y asignada correctamente.');
    }

    // ... (resto de métodos vacíos o por implementar)
    public function update(Request $request, Task $task) { /* ... */ }
    public function destroy(Task $task) { /* ... */ }
}