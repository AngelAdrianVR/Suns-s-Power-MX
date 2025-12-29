<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\ServiceOrder;
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
            'user_ids' => 'required|array|min:1', 
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
                'start_date' => now(), 
                'due_date' => $validated['due_date'] ?? null,
            ]);

            $task->assignees()->sync($validated['user_ids']);

            // 2. Notificaciones
            $assignees = User::whereIn('id', $validated['user_ids'])->get();
            $usersToNotify = $assignees->filter(fn($u) => $u->id !== Auth::id());
            if ($usersToNotify->isNotEmpty()) {
                Notification::send($usersToNotify, new TaskAssignedNotification($task, Auth::user()));
            }

            // 3. ACTUALIZACIÓN AUTOMÁTICA DE LA ORDEN
            // Si se agrega una tarea, la orden cambia a "En Proceso" (si no está ya finalizada/cancelada)
            $order = ServiceOrder::find($validated['service_order_id']);
            if ($order && !in_array($order->status, ['Instalado', 'Facturado', 'Cancelado'])) {
                $order->update(['status' => 'En Proceso']);
            }
        });

        return back()->with('success', 'Tarea creada y asignada correctamente.');
    }

    /**
     * Actualizar Tarea (Estatus y datos)
     */
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:Pendiente,En Proceso,Completado,Detenido',
            'priority' => 'sometimes|in:Baja,Media,Alta',
            'due_date' => 'nullable|date'
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        // LÓGICA DE ACTUALIZACIÓN DE ORDEN BASADA EN TAREAS
        if ($task->service_order_id) {
            $order = $task->service_order;

            // CASO 1: Si la tarea pasa a "En Proceso", la orden también (si no lo está ya)
            if ($task->status === 'En Proceso' && $order->status !== 'En Proceso') {
                // Solo si no está ya terminada o cancelada
                if (!in_array($order->status, ['Instalado', 'Facturado', 'Cancelado'])) {
                    $order->update(['status' => 'En Proceso']);
                }
            }

            // CASO 2: Si todas las tareas están terminadas, marcar orden como "Instalado"
            // Solo verificamos esto si la tarea actual se marcó como completada
            if ($task->status === 'Completado') {
                $incompleteTasks = $order->tasks()->where('status', '!=', 'Completado')->count();
                
                if ($incompleteTasks === 0 && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                    $order->update(['status' => 'Instalado']);
                }
            }
        }

        return back()->with('success', 'Tarea actualizada.');
    }

    /**
     * Eliminar Tarea
     */
    public function destroy(Task $task)
    {
        $task->delete();
        return back()->with('success', 'Tarea eliminada.');
    }
}