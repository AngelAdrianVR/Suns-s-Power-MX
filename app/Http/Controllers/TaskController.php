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
            'due_date' => 'nullable|date', // Fecha Estimada Fin
            'start_date' => 'nullable|date', // Fecha Inicio
            'finish_date' => 'nullable|date', // Fecha Real Fin
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
                'start_date' => $validated['start_date'] ?? now(), 
                'due_date' => $validated['due_date'] ?? null,
                'finish_date' => $validated['finish_date'] ?? null,
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
            if ($order && !in_array($order->status, ['Completado', 'Facturado', 'Cancelado'])) {
                $order->update(['status' => 'En Proceso']);
                
                // Si la orden pasa a En Proceso al crear tarea y no tiene fecha inicio, se la ponemos
                if (is_null($order->start_date)) {
                    $order->update(['start_date' => now()]);
                }
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
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'finish_date' => 'nullable|date'
        ]);

        $task->update($validated);

        // LÓGICA DE ACTUALIZACIÓN DE ORDEN BASADA EN TAREAS
        if ($task->service_order_id) {
            $order = $task->serviceOrder;

            if ($order) {
                // 1. Asignar Fecha de Inicio si la tarea pasa a "En Proceso" y la orden no tiene fecha
                if ($task->status === 'En Proceso' && is_null($order->start_date)) {
                    $order->update(['start_date' => now()]);
                }

                // Verificar tareas pendientes
                $incompleteTasks = $order->tasks()->where('status', '!=', 'Completado')->count();

                if ($incompleteTasks === 0) {
                    // TODAS COMPLETADAS: Marcar orden como Completado y poner fecha fin
                    if ($order->status !== 'Completado' && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update([
                            'status' => 'Completado',
                            'completion_date' => now()
                        ]);
                    }
                } else {
                    // HAY TAREAS PENDIENTES:
                    
                    // A) Si la orden estaba "Completado", debe regresar a "En Proceso" y quitar fecha fin
                    if ($order->status === 'Completado') {
                        $order->update([
                            'status' => 'En Proceso',
                            'completion_date' => null
                        ]);
                    } 
                    
                    // B) Si la tarea se puso "En Proceso" y la orden no lo estaba, actualizar orden
                    elseif ($task->status === 'En Proceso' && $order->status !== 'En Proceso' && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update(['status' => 'En Proceso']);
                        
                        // Refuerzo: asignar start_date si faltaba
                        if (is_null($order->start_date)) {
                            $order->update(['start_date' => now()]);
                        }
                    }
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