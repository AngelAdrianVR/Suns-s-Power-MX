<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Models\Task; // Importamos el modelo Task
use App\Models\User; // Importamos el modelo User
use Carbon\Carbon;   // Importamos Carbon para manejo de fechas
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * Muestra el listado de tickets filtrados por sucursal, búsqueda y pestaña activa.
     */
    public function index(Request $request)
    {
        // Agregamos municipality, state y tab a los filtros recibidos
        $filters = $request->only(['search', 'status', 'priority', 'municipality', 'state', 'tab']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;
        $tab = $filters['tab'] ?? 'pendientes'; // Por defecto mostramos pendientes

        // Contexto de sucursal
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Obtener listas para los selectores (solo de clientes de esta sucursal)
        $availableMunicipalities = Client::where('branch_id', $branchId)
            ->whereNotNull('municipality')
            ->where('municipality', '!=', '')
            ->distinct()
            ->orderBy('municipality')
            ->pluck('municipality');

        $availableStates = Client::where('branch_id', $branchId)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        $tickets = Ticket::with(['client', 'serviceOrder'])
            ->where('branch_id', $branchId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%")
                             ->orWhere('contact_person', 'like', "%{$search}%"); // Agregamos búsqueda por Alias
                      });
                });
            })
            // Lógica de separación por Pestañas y Estatus
            ->where(function ($query) use ($status, $tab) {
                if ($status) {
                    // Si el usuario filtra por un estatus específico, respetamos ese filtro
                    $query->where('status', $status);
                } else {
                    // Si no hay filtro específico, filtramos por la pestaña activa
                    if ($tab === 'terminados') {
                        $query->whereIn('status', ['Resuelto', 'Cerrado']);
                    } else {
                        // Por defecto (pendientes)
                        $query->whereIn('status', ['Abierto', 'En Análisis']);
                    }
                }
            })
            ->when($priority, function ($query, $priority) {
                $query->where('priority', $priority);
            })
            // Filtros por Ubicación del Cliente
            ->when($municipality, function ($query, $municipality) {
                $query->whereHas('client', function ($q) use ($municipality) {
                    $q->where('municipality', $municipality);
                });
            })
            ->when($state, function ($query, $state) {
                $query->whereHas('client', function ($q) use ($state) {
                    $q->where('state', $state);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'description_preview' => \Illuminate\Support\Str::limit($ticket->description, 50),
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => $ticket->created_at->format('d/m/Y H:i'),
                    'client' => $ticket->client ? [
                        'id' => $ticket->client->id,
                        'name' => $ticket->client->name,
                        'contact_person' => $ticket->client->contact_person, // Mandamos el alias
                        'phone' => $ticket->client->phone,
                        'municipality' => $ticket->client->municipality,
                        'state' => $ticket->client->state,
                    ] : null,
                    'service_order_id' => $ticket->related_service_order_id,
                ];
            });

        return Inertia::render('Ticket/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'municipalities' => $availableMunicipalities,
            'states' => $availableStates,
        ]);
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Obtenemos solo los clientes de esta sucursal
        $clients = Client::where('branch_id', $branchId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $serviceOrders = ServiceOrder::with('client:id,name')
            ->where('branch_id', $branchId)
            ->select('id', 'client_id', 'created_at', 'status') // CORRECCIÓN: Se quita 'total_amount'
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                // CORRECCIÓN: Validación por si el cliente fue eliminado
                $clientName = $order->client ? $order->client->name : 'Sin Cliente';
                return [
                    'id' => $order->id,
                    'label' => "Orden #{$order->id} - {$clientName} ({$order->created_at->format('d/m/Y')})",
                    'client_id' => $order->client_id
                ];
            });

        // Obtenemos los usuarios para asignar tareas
        $assignableUsers = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('id', '!=', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Ticket/Create', [
            'clients' => $clients,
            'serviceOrders' => $serviceOrders,
            'assignableUsers' => $assignableUsers, // Enviamos los usuarios a la vista
        ]);
    }

    /**
     * Almacena un nuevo ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'related_service_order_id' => 'nullable|exists:service_orders,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:Baja,Media,Alta,Urgente',
            'status' => 'required|in:Abierto,En Análisis,Resuelto,Cerrado',
            'evidence.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,mp4|max:10240',
            // Nuevos campos para la creación de la tarea:
            'task_duration_days' => 'nullable|integer|min:1',
            'task_user_id' => 'nullable|exists:users,id',
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $ticket = Ticket::create([
            'branch_id' => $branchId,
            'client_id' => $validated['client_id'],
            'related_service_order_id' => $validated['related_service_order_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
        ]);

        // Evitar el error de rawAddUnit al forzar (int)
        $taskDuration = !empty($validated['task_duration_days']) ? (int) $validated['task_duration_days'] : null;

        // Creación automática de la tarea SIEMPRE
        $task = Task::create([
            'title' => 'Atender Ticket #' . $ticket->id . ': ' . $ticket->title,
            'description' => $ticket->description,
            'taskable_id' => $ticket->id,
            'taskable_type' => Ticket::class,
            'branch_id' => $branchId,
            'created_by' => Auth::id(),
            'status' => in_array($validated['status'], ['Resuelto', 'Cerrado']) ? 'Completado' : 'Pendiente', // Sincronizar estado inicial
            'priority' => $ticket->priority === 'Urgente' ? 'Alta' : $ticket->priority, // Normaliza a prioridades de tareas
            'start_date' => $taskDuration ? Carbon::now() : null,
            'due_date' => $taskDuration ? Carbon::now()->addDays($taskDuration) : null,
        ]);

        if (!empty($validated['task_user_id'])) {
            $task->assignees()->attach($validated['task_user_id']);
        }

        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $ticket->addMedia($file)->toMediaCollection('ticket_evidence');
            }
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket creado correctamente.');
    }

    /**
     * Muestra el detalle del ticket.
     */
    public function show(Request $request, Ticket $ticket)
    {
        $ticket->load(['client', 'serviceOrder', 'media']);
        
        $searchComment = $request->input('search_comment');

        $comments = $ticket->comments()
            ->with(['user']) 
            ->when($searchComment, function ($query, $search) {
                $query->where('body', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'profile_photo_url' => $comment->user->profile_photo_url,
                    ] : null,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at, 
                    'is_staff' => true, 
                    'attachments' => [],
                ];
            });

        return Inertia::render('Ticket/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'resolution_notes' => $ticket->resolution_notes,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
                'client' => $ticket->client,
                'service_order' => $ticket->serviceOrder,
                'media' => $ticket->getMedia('ticket_evidence')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->human_readable_size,
                    ];
                }),
            ],
            'conversation_history' => $comments,
            'filters' => [
                'search_comment' => $searchComment,
            ],
        ]);
    }

    /**
     * Agrega una respuesta (comentario) al ticket.
     */
    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'new_status' => 'nullable|in:Abierto,En Análisis,Resuelto,Cerrado', 
        ]);

        $comment = $ticket->comments()->create([
            'body' => $validated['body'],
            'user_id' => Auth::id(),
        ]);

        if (!empty($validated['new_status']) && $validated['new_status'] !== $ticket->status) {
            $ticket->status = $validated['new_status'];
            $ticket->save();

            // Sincronizar tareas al responder
            if (in_array($validated['new_status'], ['Resuelto', 'Cerrado'])) {
                Task::where('taskable_type', Ticket::class)
                    ->where('taskable_id', $ticket->id)
                    ->where('status', '!=', 'Completado')
                    ->update([
                        'status' => 'Completado',
                        'finish_date' => now()
                    ]);
            }
        } else {
            $ticket->touch(); 
        }

        return redirect()->back()->with('success', 'Respuesta agregada correctamente.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(Ticket $ticket)
    {
        $ticket->load(['media']);
        
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $clients = Client::where('branch_id', $branchId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $serviceOrders = ServiceOrder::with('client:id,name')
            ->where('branch_id', $branchId)
            ->select('id', 'client_id', 'created_at', 'status')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                // CORRECCIÓN: Validación por si el cliente fue eliminado
                $clientName = $order->client ? $order->client->name : 'Sin Cliente';
                return [
                    'id' => $order->id,
                    'label' => "Orden #{$order->id} - {$clientName} ({$order->created_at->format('d/m/Y')})",
                    'client_id' => $order->client_id
                ];
            });

        // Usuarios disponibles
        $assignableUsers = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('id', '!=', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Buscar si existe una tarea asociada a este ticket para precargar duracion/usuario
        $task = Task::where('taskable_type', Ticket::class)
            ->where('taskable_id', $ticket->id)
            ->with('assignees')
            ->first();

        $taskDuration = null;
        $taskUserId = null;

        if ($task) {
            if ($task->start_date && $task->due_date) {
                $taskDuration = (int) Carbon::parse($task->start_date)->diffInDays(Carbon::parse($task->due_date));
                if ($taskDuration === 0) $taskDuration = 1; 
            }
            $assignee = $task->assignees->first();
            if ($assignee) {
                $taskUserId = $assignee->id;
            }
        }

        return Inertia::render('Ticket/Edit', [
            'ticket' => [
                'id' => $ticket->id,
                'client_id' => $ticket->client_id,
                'related_service_order_id' => $ticket->related_service_order_id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'resolution_notes' => $ticket->resolution_notes,
                'task_duration_days' => $taskDuration, // Se pasa a la vista
                'task_user_id' => $taskUserId,         // Se pasa a la vista
                'evidence' => $ticket->getMedia('ticket_evidence')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->file_name,
                    ];
                }),
            ],
            'clients' => $clients,
            'serviceOrders' => $serviceOrders, 
            'assignableUsers' => $assignableUsers, // Pasamos usuarios a la vista
        ]);
    }

    /**
     * Actualiza el ticket en base de datos.
     */
    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'related_service_order_id' => 'nullable|exists:service_orders,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:Baja,Media,Alta,Urgente',
            'status' => 'required|in:Abierto,En Análisis,Resuelto,Cerrado',
            'resolution_notes' => 'nullable|string',
            'evidence.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,mp4|max:10240',
            // Campos de la tarea
            'task_duration_days' => 'nullable|integer|min:1',
            'task_user_id' => 'nullable|exists:users,id',
        ]);

        $ticket->update([
            'client_id' => $validated['client_id'],
            'related_service_order_id' => $validated['related_service_order_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'] ?? null,
        ]);

        // Evitar el error de rawAddUnit al forzar (int)
        $taskDuration = !empty($validated['task_duration_days']) ? (int) $validated['task_duration_days'] : null;

        // Actualizar o Crear la tarea asociada SIEMPRE
        $task = Task::where('taskable_type', Ticket::class)
            ->where('taskable_id', $ticket->id)
            ->first();

        $startDate = $task && $task->start_date ? $task->start_date : ($taskDuration ? Carbon::now() : null);
        $dueDate = $taskDuration ? ($startDate ? Carbon::parse($startDate)->addDays($taskDuration) : Carbon::now()->addDays($taskDuration)) : ($task ? $task->due_date : null);

        // Si se resolvió el ticket manualmente, marcamos completada la tarea
        $newStatus = in_array($validated['status'], ['Resuelto', 'Cerrado']) ? 'Completado' : ($task ? $task->status : 'Pendiente');
        // Si el estado de la tarea estaba completada pero regresaron el ticket a abierto, regresamos la tarea a pendiente
        if (in_array($validated['status'], ['Abierto', 'En Análisis']) && $newStatus === 'Completado') {
            $newStatus = 'Pendiente';
        }

        if ($task) {
            // Actualiza
            $task->update([
                'title' => 'Atender Ticket #' . $ticket->id . ': ' . $ticket->title,
                'description' => $ticket->description,
                'priority' => $ticket->priority === 'Urgente' ? 'Alta' : $ticket->priority,
                'status' => $newStatus,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'finish_date' => $newStatus === 'Completado' ? ($task->finish_date ?? now()) : null
            ]);
        } else {
            // Crea si no existía (por si fue eliminada manualmente)
            $task = Task::create([
                'title' => 'Atender Ticket #' . $ticket->id . ': ' . $ticket->title,
                'description' => $ticket->description,
                'taskable_id' => $ticket->id,
                'taskable_type' => Ticket::class,
                'branch_id' => session('current_branch_id') ?? Auth::user()->branch_id,
                'created_by' => Auth::id(),
                'status' => $newStatus,
                'priority' => $ticket->priority === 'Urgente' ? 'Alta' : $ticket->priority,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'finish_date' => $newStatus === 'Completado' ? now() : null
            ]);
        }

        // Sincroniza al responsable
        if (!empty($validated['task_user_id'])) {
            $task->assignees()->sync([$validated['task_user_id']]);
        } else {
            $task->assignees()->detach();
        }

        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $ticket->addMedia($file)->toMediaCollection('ticket_evidence');
            }
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket actualizado correctamente.');
    }

    /**
     * Elimina el ticket.
     */
    public function destroy(Ticket $ticket)
    {
        // Eliminar la tarea asociada al ticket antes de eliminar el ticket
        Task::where('taskable_type', Ticket::class)
            ->where('taskable_id', $ticket->id)
            ->delete();

        $ticket->delete();
        return redirect()->back()->with('success', 'Ticket y tareas relacionadas eliminados.');
    }

    /**
     * Actualización rápida de estatus.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:Abierto,En Análisis,Resuelto,Cerrado'
        ]);

        $ticket->update(['status' => $validated['status']]);

        // Sincronizar tareas al cambio rápido de estatus
        if (in_array($validated['status'], ['Resuelto', 'Cerrado'])) {
            Task::where('taskable_type', Ticket::class)
                ->where('taskable_id', $ticket->id)
                ->where('status', '!=', 'Completado')
                ->update([
                    'status' => 'Completado',
                    'finish_date' => now()
                ]);
        }

        return back()->with('success', 'Estatus actualizado.');
    }
}