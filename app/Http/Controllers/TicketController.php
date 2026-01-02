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
        $filters = $request->only(['search', 'status', 'priority']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;

        // Contexto de sucursal
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $tickets = Ticket::with(['client', 'serviceOrder'])
            ->where('branch_id', $branchId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%") // Agregado: Búsqueda en descripción
                      ->orWhere('id', 'like', "%{$search}%") // Búsqueda por folio
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
            ->orderBy('created_at', 'desc') // Los más recientes primero
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
                        'phone' => $ticket->client->phone,
                    ] : null,
                    'service_order_id' => $ticket->related_service_order_id,
                ];
            });

        return Inertia::render('Ticket/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
        ]);
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create()
    {
        // Cargamos clientes para el select. 
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        
        return Inertia::render('Ticket/Create', [
            'clients' => $clients,
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
            'evidence.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,mp4|max:10240', // 10MB max
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

        // Manejo de Evidencias (Múltiples archivos)
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $ticket->addMedia($file)->toMediaCollection('ticket_evidence');
            }
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket creado correctamente.');
    }

    /**
     * Muestra el detalle del ticket con funcionalidad de búsqueda en comentarios.
     */
    public function show(Request $request, Ticket $ticket)
    {
        // Eager Loading de relaciones principales
        $ticket->load(['client', 'serviceOrder', 'media', 'convertedOrder']);
        
        // Obtener búsqueda de comentarios (si existe)
        $searchComment = $request->input('search_comment');

        // Cargar comentarios/respuestas con filtro opcional
        $comments = $ticket->comments()
            ->with(['user', 'media']) // Asumiendo relación 'comments' en el modelo Ticket
            ->when($searchComment, function ($query, $search) {
                $query->where('body', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'asc') // Historial cronológico
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'profile_photo_url' => $comment->user->profile_photo_url, // Compatible con Jetstream
                    ] : null,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at->format('d/m/Y H:i'),
                    'is_staff' => true, // Bandera por si quieres diferenciar estilos
                    'attachments' => $comment->getMedia('comment_attachments')->map(function ($media) {
                         return [
                            'id' => $media->id,
                            'url' => $media->getUrl(),
                            'name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                         ];
                    }),
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
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
                'updated_at' => $ticket->updated_at->format('d/m/Y H:i'),
                'client' => $ticket->client,
                'service_order' => $ticket->serviceOrder,
                'converted_order' => $ticket->convertedOrder,
                // Mapeamos los medios del ticket principal
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
            'comments' => $comments, // Pasamos los comentarios (timeline)
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
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        // Crear el comentario asociado al ticket y al usuario actual
        // Asumiendo relación polimórfica o directa. Ajusta según tu modelo Comment.
        $comment = $ticket->comments()->create([
            'body' => $validated['body'],
            'user_id' => Auth::id(),
            // 'branch_id' => ... si los comentarios pertenecen a sucursal
        ]);

        // Manejo de adjuntos en el comentario
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $comment->addMedia($file)->toMediaCollection('comment_attachments');
            }
        }

        // Opcional: Actualizar el estatus del ticket si estaba cerrado y se recibe respuesta
        // if ($ticket->status === 'Cerrado') {
        //     $ticket->update(['status' => 'Abierto']);
        // }
        // O actualizar el 'updated_at' del ticket para que suba en la lista
        $ticket->touch();

        return redirect()->back()->with('success', 'Respuesta agregada correctamente.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(Ticket $ticket)
    {
        $ticket->load(['media']);
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        
        $relatedOrders = [];
        if ($ticket->client_id) {
            $relatedOrders = ServiceOrder::where('client_id', $ticket->client_id)
                ->select('id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($order) {
                    return ['value' => $order->id, 'label' => 'Orden #' . $order->id . ' - ' . $order->created_at->format('d/m/Y')];
                });
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
                'evidence' => $ticket->getMedia('ticket_evidence')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->file_name,
                    ];
                }),
            ],
            'clients' => $clients,
            'client_orders' => $relatedOrders,
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
     * Actualización rápida de estatus (útil para Kanban o listas rápidas).
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