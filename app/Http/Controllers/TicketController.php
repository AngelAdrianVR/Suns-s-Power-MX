<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * Muestra el listado de tickets filtrados por sucursal y búsqueda.
     */
    public function index(Request $request)
    {
        // Agregamos municipality y state a los filtros recibidos
        $filters = $request->only(['search', 'status', 'priority', 'municipality', 'state']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;

        // Contexto de sucursal
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Obtener listas para los selectores (solo de clientes de esta sucursal)
        // Usamos distinct para no repetir opciones
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
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
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
                        'phone' => $ticket->client->phone, // Nota: Si eliminaste 'phone' de Client, ajusta esto a contact_person o relations
                        'municipality' => $ticket->client->municipality, // Útil si quieres mostrarlo en tabla
                        'state' => $ticket->client->state,
                    ] : null,
                    'service_order_id' => $ticket->related_service_order_id,
                ];
            });

        return Inertia::render('Ticket/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'municipalities' => $availableMunicipalities, // Pasamos las opciones a la vista
            'states' => $availableStates,
        ]);
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create()
    {
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $serviceOrders = ServiceOrder::with('client:id,name')
            ->where('branch_id', $branchId)
            ->select('id', 'client_id', 'created_at', 'total_amount', 'status')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'label' => "Orden #{$order->id} - {$order->client->name} ({$order->created_at->format('d/m/Y')})",
                    'client_id' => $order->client_id
                ];
            });
        
        return Inertia::render('Ticket/Create', [
            'clients' => $clients,
            'serviceOrders' => $serviceOrders,
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
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        
        // Replicamos la carga de órdenes de servicio como en 'create' para permitir cambiarla
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $serviceOrders = ServiceOrder::with('client:id,name')
            ->where('branch_id', $branchId)
            ->select('id', 'client_id', 'created_at', 'status')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'label' => "Orden #{$order->id} - {$order->client->name} ({$order->created_at->format('d/m/Y')})",
                    'client_id' => $order->client_id
                ];
            });

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
                'evidence' => $ticket->getMedia('ticket_evidence')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->file_name,
                    ];
                }),
            ],
            'clients' => $clients,
            // Enviamos 'serviceOrders' (todas las disponibles) en lugar de solo 'client_orders'
            'serviceOrders' => $serviceOrders, 
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
        $ticket->delete();
        return redirect()->back()->with('success', 'Ticket eliminado.');
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

        return back()->with('success', 'Estatus actualizado.');
    }
}