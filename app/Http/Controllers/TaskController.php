<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TaskController extends Controller
{
    /**
     * Muestra el Dashboard PMS (Kanban Semanal)
     */
    public function index(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Determinar inicio de semana (Lunes)
        $dateParam = $request->input('week_start');
        $weekStart = $dateParam ? Carbon::parse($dateParam)->startOfWeek() : Carbon::now()->startOfWeek();
        
        // Termina el Sábado (5 días después del lunes) para mostrar la semana completa
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay(); 

        // Generar estructura de días (Lunes a Sábado) para el frontend
        $days = [];
        for ($i = 0; $i <= 5; $i++) {
            $currentDay = $weekStart->copy()->addDays($i);
            $days[] = [
                'date' => $currentDay->format('Y-m-d'),
                'day_name' => ucfirst($currentDay->locale('es')->isoFormat('dddd')),
                'day_number' => $currentDay->format('d'),
            ];
        }

        // QUERY BASE: Tareas ASIGNADAS en esa semana
        $assignedTasksQuery = Task::with(['assignees', 'taskable', 'comments.user'])
            ->where('branch_id', $branchId)
            ->has('assignees')
            ->whereBetween('start_date', [$weekStart->startOfDay(), $weekEnd]);

        // RESTRICCIÓN DE PERMISO: Si NO tiene view_all, solo ve tareas donde esté asignado
        if (!Auth::user()->can('pms.view_all')) {
            $assignedTasksQuery->whereHas('assignees', function($q) {
                $q->where('users.id', Auth::id());
            });
        }

        $assignedTasks = $assignedTasksQuery->get()->groupBy(function($task) {
            return Carbon::parse($task->start_date)->format('Y-m-d');
        });

        // RESTRICCIÓN DE PERMISO: Tareas SIN ASIGNAR (Backlog) solo visibles para view_all
        $unassignedTasks = [];
        if (Auth::user()->can('pms.view_all')) {
            $unassignedTasks = Task::with(['taskable'])
                ->where('branch_id', $branchId)
                ->doesntHave('assignees')
                ->whereNotIn('status', ['Completado', 'Cancelado'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Usuarios disponibles para asignar al arrastrar tarjetas
        $assignableUsers = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('id', '!=', 1) // Excluir usuario admin
            ->get(['id', 'name', 'phone', 'profile_photo_path']);

        // Data de Módulos para selects
        $serviceOrders = ServiceOrder::whereNotIn('status', ['Completado', 'Facturado', 'Cancelado'])
            ->where('branch_id', $branchId)
            ->get(['id', 'service_number']);
            
        $tickets = Ticket::whereNotIn('status', ['Resuelto', 'Cerrado'])
            ->where('branch_id', $branchId)
            ->get(['id', 'title']);

        return Inertia::render('PMS/Index', [
            'week_start' => $weekStart->format('Y-m-d'),
            'prev_week' => $weekStart->copy()->subWeek()->format('Y-m-d'),
            'next_week' => $weekStart->copy()->addWeek()->format('Y-m-d'),
            'days' => $days,
            'assigned_tasks' => $assignedTasks,
            'unassigned_tasks' => $unassignedTasks,
            'assignable_users' => $assignableUsers,
            'service_orders' => $serviceOrders,
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(!Auth::user()->can('pms.create'), 403, 'No tienes permiso para crear tareas.');

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'taskable_id' => 'nullable|integer',
            'taskable_type' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date', 
            'start_date' => 'nullable|date', 
            'finish_date' => 'nullable|date', 
            'priority' => 'required|in:Baja,Media,Alta',
            'user_ids' => 'nullable|array', 
            'user_ids.*' => 'exists:users,id'
        ]);

        DB::transaction(function () use ($validated, $branchId) {
            // 1. Crear Tarea
            $task = Task::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'taskable_id' => $validated['taskable_id'] ?? null,
                'taskable_type' => $validated['taskable_type'] ?? null,
                'branch_id' => $branchId,
                'created_by' => Auth::id(),
                'status' => 'Pendiente',
                'priority' => $validated['priority'],
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'finish_date' => $validated['finish_date'] ?? null,
            ]);

            if (!empty($validated['user_ids'])) {
                $task->assignees()->sync($validated['user_ids']);

                // 2. Notificaciones
                $assignees = User::whereIn('id', $validated['user_ids'])->get();
                $usersToNotify = $assignees->filter(fn($u) => $u->id !== Auth::id());
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new TaskAssignedNotification($task, Auth::user()));
                }
            }

            // 3. ACTUALIZACIÓN AUTOMÁTICA DEL MÓDULO ASOCIADO
            if (isset($validated['taskable_type'])) {
                if ($validated['taskable_type'] === 'App\\Models\\ServiceOrder') {
                    $order = ServiceOrder::find($validated['taskable_id']);
                    // FIX 1: Quitamos 'Completado' del array para que si se agrega una tarea, 
                    // regrese obligatoriamente a 'En Proceso' y limpie la fecha de finalización.
                    if ($order && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update([
                            'status' => 'En Proceso',
                            'completion_date' => null
                        ]);
                        
                        if (is_null($order->start_date)) {
                            $order->update(['start_date' => now()]);
                        }
                    }
                } elseif ($validated['taskable_type'] === 'App\\Models\\Ticket') {
                    $ticket = Ticket::find($validated['taskable_id']);
                    if ($ticket && $ticket->status === 'Abierto') {
                        $ticket->update(['status' => 'En Análisis']);
                    }
                }
            }
        });

        return back()->with('success', 'Tarea creada correctamente.');
    }

    /**
     * Actualizar Tarea (Estatus y datos)
     */
    public function update(Request $request, Task $task)
    {
        // Determinar el tipo de actualización para verificar permisos
        $isFullEdit = $request->hasAny(['title', 'description', 'priority', 'due_date']);
        $isSchedule = $request->hasAny(['start_date', 'user_ids']);
        
        if ($isFullEdit) {
            abort_if(!Auth::user()->can('pms.edit'), 403, 'No tienes permiso para editar tareas.');
        } elseif ($isSchedule) {
            // Si solo se mueve de día, se asigna o DESASIGNA en calendario
            abort_if(!Auth::user()->can('pms.schedule') && !Auth::user()->can('pms.edit'), 403, 'No tienes permiso para reprogramar tareas.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:Pendiente,En Proceso,Completado,Detenido',
            'priority' => 'sometimes|in:Baja,Media,Alta',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'finish_date' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'taskable_type' => 'nullable|string',
            'taskable_id' => 'nullable|integer'
        ]);

        // --- LÓGICA DE FECHAS AUTOMÁTICAS ---
        if (isset($validated['status'])) {
            $newStatus = $validated['status'];
            
            // 1. Al iniciar "En Proceso": Guardar fecha inicio solo si no existe.
            if ($newStatus === 'En Proceso') {
                if (is_null($task->start_date)) {
                    $validated['start_date'] = now();
                }
            }

            // 2. Al cambiar a "Completado": Agregar fecha fin.
            if ($newStatus === 'Completado') {
                $validated['finish_date'] = now();
            } else {
                // 3. Si cambia a cualquier otro estatus (y deja de ser completado), borrar fecha fin.
                $validated['finish_date'] = null;
            }
        }
        // ------------------------------------

        $task->update($validated);

        // Actualizar asignados si se enviaron en la petición (incluso si es un arreglo vacío para desasignar)
        if ($request->has('user_ids')) {
            $task->assignees()->sync($validated['user_ids'] ?? []);
        }

        // LÓGICA DE ACTUALIZACIÓN DE MÓDULO BASADA EN TAREAS
        if ($task->taskable_type === 'App\\Models\\ServiceOrder') {
            $order = clone $task->taskable; 

            if ($order) {
                if ($task->status === 'En Proceso' && is_null($order->start_date)) {
                    $order->update(['start_date' => now()]);
                }

                $incompleteTasks = $order->tasks()->where('status', '!=', 'Completado')->count();

                if ($incompleteTasks === 0) {
                    // Verificar si el formulario de material ya fue contestado
                    $unreportedCount = $order->items()->whereNull('used_quantity')->count();
                    
                    if ($unreportedCount === 0) {
                        // Tareas terminadas y material conciliado -> Se auto-completa
                        if (!in_array($order->status, ['Completado', 'Facturado', 'Cancelado'])) {
                            $order->update([
                                'status' => 'Completado',
                                'completion_date' => now()
                            ]);
                        }
                    } else {
                        // Tareas terminadas pero falta conciliar material -> Se queda en proceso
                        if (in_array($order->status, ['Cotización', 'Aceptado', 'Pendiente'])) {
                            $order->update(['status' => 'En Proceso']);
                        }
                    }
                } else {
                    if ($order->status === 'Completado') {
                        $order->update([
                            'status' => 'En Proceso',
                            'completion_date' => null
                        ]);
                    } 
                    elseif ($task->status === 'En Proceso' && $order->status !== 'En Proceso' && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update(['status' => 'En Proceso']);
                        if (is_null($order->start_date)) {
                            $order->update(['start_date' => now()]);
                        }
                    }
                }
            }
        } elseif ($task->taskable_type === 'App\\Models\\Ticket') {
            $ticket = clone $task->taskable;
            if ($ticket && $task->status === 'En Proceso' && $ticket->status === 'Abierto') {
                $ticket->update(['status' => 'En Análisis']);
            }
        }

        return back()->with('success', 'Tarea actualizada exitosamente.');
    }

    public function destroy(Task $task)
    {
        abort_if(!Auth::user()->can('pms.delete'), 403, 'No tienes permiso para eliminar tareas.');
        
        $task->delete();
        return back()->with('success', 'Tarea eliminada exitosamente.');
    }
}