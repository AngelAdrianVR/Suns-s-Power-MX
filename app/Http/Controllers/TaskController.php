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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Inertia\Inertia;

class TaskController extends Controller
{
    /**
     * Muestra el Dashboard PMS (Kanban Semanal y Vista Lista)
     */
    public function index(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // --- 1. ENDPOINTS AJAX PARA SCROLL (CARGA PEREZOSA DESDE BACKEND) ---
        if ($request->wantsJson() && $request->has('lazy_load')) {
            $type = $request->input('lazy_load');
            $offset = (int) $request->input('offset', 0);
            $limit = 10;

            if ($type === 'kanban_day') {
                $targetDate = Carbon::parse($request->input('date'))->startOfDay();
                
                $dayTasksQuery = Task::with([
                    'assignees', 'comments.user', 'requiredEvidences.media',
                    'taskable' => function (MorphTo $morphTo) {
                        $morphTo->morphWith([
                            ServiceOrder::class => ['client', 'technician', 'salesRep', 'items.product'],
                            Ticket::class => ['client', 'serviceOrder']
                        ]);
                    }
                ])
                ->where('branch_id', $branchId)
                ->has('assignees')
                ->whereNotNull('start_date') 
                ->where(function ($query) use ($targetDate) {
                    $query->where('start_date', '<=', $targetDate->copy()->endOfDay())
                          ->where(function($q) use ($targetDate) {
                              $q->where('due_date', '>=', $targetDate)
                                ->orWhereNull('due_date')
                                ->orWhere('start_date', '>=', $targetDate);
                          });
                });

                if (!Auth::user()->can('pms.view_all')) {
                    $dayTasksQuery->whereHas('assignees', function($q) { $q->where('users.id', Auth::id()); });
                }

                $tasks = $dayTasksQuery->orderBy('start_date', 'asc')->orderBy('id', 'asc')
                                       ->skip($offset)->take($limit)->get();
                
                return response()->json($tasks);
            }

            if ($type === 'backlog_unassigned' || $type === 'backlog_nodate') {
                $backlogQuery = Task::with([
                    'assignees', 'comments.user', 'requiredEvidences.media',
                    'taskable' => function (MorphTo $morphTo) {
                        $morphTo->morphWith([
                            ServiceOrder::class => ['client', 'technician', 'salesRep', 'items.product'],
                            Ticket::class => ['client', 'serviceOrder']
                        ]);
                    }
                ])
                ->where('branch_id', $branchId)
                ->whereNotIn('status', ['Completado', 'Cancelado']);

                if ($type === 'backlog_unassigned') {
                    $backlogQuery->doesntHave('assignees');
                } else {
                    $backlogQuery->has('assignees')->whereNull('start_date');
                }

                if (!Auth::user()->can('pms.view_all')) {
                    $backlogQuery->whereHas('assignees', function($q) { $q->where('users.id', Auth::id()); });
                }

                $tasks = $backlogQuery->orderBy('created_at', 'desc')->skip($offset)->take($limit)->get();
                return response()->json($tasks);
            }
        }

        // --- 2. CARGA INICIAL NORMAL DE INERTIA ---
        $dateParam = $request->input('week_start');
        $weekStart = $dateParam ? Carbon::parse($dateParam)->startOfWeek() : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay(); 

        $days = [];
        for ($i = 0; $i <= 5; $i++) {
            $currentDay = $weekStart->copy()->addDays($i);
            $days[] = [
                'date' => $currentDay->format('Y-m-d'),
                'day_name' => ucfirst($currentDay->locale('es')->isoFormat('dddd')),
                'day_number' => $currentDay->format('d'),
            ];
        }

        // --- QUERY 1: TAREAS ASIGNADAS (KANBAN) ---
        $assignedTasksQuery = Task::with([
            'assignees', 'comments.user', 'requiredEvidences.media',
            'taskable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    ServiceOrder::class => ['client', 'technician', 'salesRep', 'items.product'],
                    Ticket::class => ['client', 'serviceOrder']
                ]);
            }
        ])
        ->where('branch_id', $branchId)
        ->has('assignees')
        ->whereNotNull('start_date') 
        ->where(function ($query) use ($weekStart, $weekEnd) {
            $query->where('start_date', '<=', $weekEnd)
                  ->where(function($q) use ($weekStart) {
                      $q->where('due_date', '>=', $weekStart->startOfDay())
                        ->orWhereNull('due_date')
                        ->orWhere('start_date', '>=', $weekStart->startOfDay()); 
                  });
        })
        ->orderBy('start_date', 'asc')->orderBy('id', 'asc'); // Consistencia para la paginación

        if (!Auth::user()->can('pms.view_all')) {
            $assignedTasksQuery->whereHas('assignees', function($q) { $q->where('users.id', Auth::id()); });
        }

        $fetchedTasks = $assignedTasksQuery->get();
        $assignedTasks = [];

        // Inicializar los días en el array
        foreach ($days as $day) {
            $assignedTasks[$day['date']] = [];
        }

        foreach ($fetchedTasks as $task) {
            $taskStart = Carbon::parse($task->start_date)->startOfDay();
            $taskEnd = $task->due_date ? Carbon::parse($task->due_date)->startOfDay() : $taskStart->copy();

            if ($taskEnd->lt($taskStart)) {
                $taskEnd = $taskStart->copy();
            }

            $currentDate = $taskStart->copy();
            while ($currentDate->lte($taskEnd)) {
                $dateString = $currentDate->format('Y-m-d');
                if ($currentDate->between($weekStart->startOfDay(), $weekEnd)) {
                    $assignedTasks[$dateString][] = $task;
                }
                $currentDate->addDay();
            }
        }

        // Cortar la información pesada: Solo enviar máximo 10 al frontend
        $assignedTasksLimited = [];
        $hasMoreTasks = ['kanban' => [], 'backlog_unassigned' => false, 'backlog_nodate' => false];

        foreach ($days as $day) {
            $dateStr = $day['date'];
            $tasksForDay = $assignedTasks[$dateStr];
            $hasMoreTasks['kanban'][$dateStr] = count($tasksForDay) > 10;
            $assignedTasksLimited[$dateStr] = array_slice($tasksForDay, 0, 10);
        }

        // --- QUERY 2: TAREAS SIN ASIGNAR (BACKLOG) ---
        $unassignedBacklog = [];
        $noDateBacklog = [];

        if (Auth::user()->can('pms.view_all')) {
            $allUnassigned = Task::with([
                'assignees', 'comments.user', 'requiredEvidences.media',
                'taskable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        ServiceOrder::class => ['client', 'technician', 'salesRep', 'items.product'],
                        Ticket::class => ['client', 'serviceOrder']
                    ]);
                }
            ])
            ->where('branch_id', $branchId)
            ->where(function ($query) {
                $query->doesntHave('assignees')->orWhereNull('start_date'); 
            })
            ->whereNotIn('status', ['Completado', 'Cancelado'])
            ->orderBy('created_at', 'desc')
            ->get();

            foreach ($allUnassigned as $task) {
                if ($task->assignees->isEmpty()) {
                    $unassignedBacklog[] = $task;
                } else {
                    $noDateBacklog[] = $task;
                }
            }

            $hasMoreTasks['backlog_unassigned'] = count($unassignedBacklog) > 10;
            $hasMoreTasks['backlog_nodate'] = count($noDateBacklog) > 10;
        }

        $unassignedTasksLimited = array_merge(
            array_slice($unassignedBacklog, 0, 10),
            array_slice($noDateBacklog, 0, 10)
        );

        // --- QUERY 3: TODAS LAS TAREAS (VISTA LISTA Y MÉTRICAS) ---
        $allTasksQuery = Task::with([
            'assignees', 'comments.user', 'requiredEvidences.media',
            'taskable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    ServiceOrder::class => ['client', 'technician', 'salesRep', 'items.product'],
                    Ticket::class => ['client', 'serviceOrder']
                ]);
            }
        ])->where('branch_id', $branchId);

        if (!Auth::user()->can('pms.view_all')) {
            $allTasksQuery->whereHas('assignees', function($q) { $q->where('users.id', Auth::id()); });
        }
        
        // LIMITAMOS A 300 PARA EVITAR QUE LA BASE DE DATOS COLAPSE CON EL HISTORIAL
        $allTasks = $allTasksQuery->orderBy('created_at', 'desc')->limit(300)->get();

        // Usuarios disponibles
        $assignableUsers = User::where('branch_id', $branchId)
            ->where('is_active', true)->where('id', '!=', 1) 
            ->get(['id', 'name', 'phone', 'profile_photo_path']);

        // Data Selects
        $serviceOrders = ServiceOrder::with('client:id,name')->whereNotIn('status', ['Completado', 'Facturado', 'Cancelado'])
            ->where('branch_id', $branchId)->select('id', 'client_id', 'created_at', 'status')->orderBy('created_at', 'desc')->get()
            ->map(function ($order) {
                $clientName = $order->client ? $order->client->name : 'Sin Cliente';
                return [ 'id' => $order->id, 'label' => "Orden #{$order->id} - {$clientName} ({$order->created_at->format('d/m/Y')})", 'client' => $order->client ];
            });
            
        $tickets = Ticket::with('client:id,name')->whereNotIn('status', ['Resuelto', 'Cerrado'])
            ->where('branch_id', $branchId)->select('id', 'client_id', 'title', 'created_at')->orderBy('created_at', 'desc')->get()
            ->map(function ($ticket) {
                $clientName = $ticket->client ? $ticket->client->name : 'Sin Cliente';
                return [ 'id' => $ticket->id, 'label' => "Ticket #{$ticket->id} - {$clientName} ({$ticket->title})", 'client' => $ticket->client ];
            });

        return Inertia::render('PMS/Index', [
            'week_start' => $weekStart->format('Y-m-d'),
            'prev_week' => $weekStart->copy()->subWeek()->format('Y-m-d'),
            'next_week' => $weekStart->copy()->addWeek()->format('Y-m-d'),
            'days' => $days,
            'assigned_tasks' => $assignedTasksLimited,
            'unassigned_tasks' => $unassignedTasksLimited,
            'has_more_tasks' => $hasMoreTasks, // <- Nueva bandera
            'all_tasks' => $allTasks,
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
                $assignees = User::whereIn('id', $validated['user_ids'])->get();
                $usersToNotify = $assignees->filter(fn($u) => $u->id !== Auth::id());
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new TaskAssignedNotification($task, Auth::user()));
                }
            }

            if (isset($validated['taskable_type'])) {
                if ($validated['taskable_type'] === 'App\\Models\\ServiceOrder') {
                    $order = ServiceOrder::find($validated['taskable_id']);
                    if ($order && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update(['status' => 'En Proceso', 'completion_date' => null]);
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

     public function update(Request $request, Task $task)
    {
        $user = Auth::user();
        
        $isFullEdit = $request->hasAny(['title', 'description', 'priority', 'due_date']);
        $isSchedule = $request->hasAny(['start_date', 'user_ids']);
        $isStatusChange = $request->has('status');
        
        if ($isFullEdit) {
            abort_if(!$user->can('pms.edit'), 403, 'No tienes permiso para editar tareas.');
        } elseif ($isSchedule) {
            abort_if(!$user->can('pms.schedule') && !$user->can('pms.edit'), 403, 'No tienes permiso para reprogramar tareas.');
        }

        if ($isStatusChange) {
            if (!$user->can('pms.schedule')) {
                $isAssigned = $task->assignees()->where('users.id', $user->id)->exists();
                abort_if(!$isAssigned, 403, 'Sólo puedes cambiar el estatus de las tareas asignadas a ti.');
            }
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

        if (isset($validated['status'])) {
            $newStatus = $validated['status'];
            if ($newStatus === 'En Proceso' && is_null($task->start_date)) {
                $validated['start_date'] = now();
            }
            if ($newStatus === 'Completado') {
                $task->loadMissing('requiredEvidences.media');
                $pendingEvidences = $task->requiredEvidences->filter(function($ev) {
                    return $ev->media->isEmpty();
                })->count();

                if ($pendingEvidences > 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => "No se puede completar la tarea. Faltan {$pendingEvidences} evidencias fotográficas requeridas en la Orden."
                    ]);
                }

                $validated['finish_date'] = now();
            } else {
                $validated['finish_date'] = null;
            }
        }

        $task->update($validated);

        if ($request->has('user_ids')) {
            $task->assignees()->sync($validated['user_ids'] ?? []);
        }

        if ($task->taskable_type === 'App\\Models\\ServiceOrder' && $task->taskable) {
            $order = clone $task->taskable; 
            if ($order) {
                if ($task->status === 'En Proceso' && is_null($order->start_date)) {
                    $order->update(['start_date' => now()]);
                }
                $incompleteTasks = $order->tasks()->where('status', '!=', 'Completado')->count();
                if ($incompleteTasks === 0) {
                    $unreportedCount = $order->items()->whereNull('used_quantity')->count();
                    if ($unreportedCount === 0) {
                        if (!in_array($order->status, ['Completado', 'Facturado', 'Cancelado'])) {
                            $order->update(['status' => 'Completado', 'completion_date' => now()]);
                        }
                    } else {
                        if (in_array($order->status, ['Cotización', 'Aceptado', 'Pendiente'])) {
                            $order->update(['status' => 'En Proceso']);
                        }
                    }
                } else {
                    if ($order->status === 'Completado') {
                        $order->update(['status' => 'En Proceso', 'completion_date' => null]);
                    } 
                    elseif ($task->status === 'En Proceso' && $order->status !== 'En Proceso' && !in_array($order->status, ['Facturado', 'Cancelado'])) {
                        $order->update(['status' => 'En Proceso']);
                        if (is_null($order->start_date)) {
                            $order->update(['start_date' => now()]);
                        }
                    }
                }
            }
        } elseif ($task->taskable_type === 'App\\Models\\Ticket' && $task->taskable) {
            $ticket = clone $task->taskable;
            if ($ticket) {
                if ($task->status === 'En Proceso' && $ticket->status === 'Abierto') {
                    $ticket->update(['status' => 'En Análisis']);
                }
                if ($task->status === 'Completado') {
                    $incompleteTasks = Task::where('taskable_type', 'App\\Models\\Ticket')
                                            ->where('taskable_id', $ticket->id)
                                            ->where('status', '!=', 'Completado')
                                            ->count();
                    if ($incompleteTasks === 0 && !in_array($ticket->status, ['Resuelto', 'Cerrado'])) {
                        $ticket->update(['status' => 'Resuelto']);
                    }
                } else {
                    if ($ticket->status === 'Resuelto') {
                         $ticket->update(['status' => 'En Análisis']);
                    }
                }
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