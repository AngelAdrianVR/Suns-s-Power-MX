<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL; // Importante para Signed Routes

class ServiceOrderController extends Controller
{
    /**
     * Muestra el listado de órdenes de servicio (Dashboard Operativo).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;
        
        // 1. Determinar permisos (Igual que en Show)
        // Admin y Ventas ven todo. Los demás solo lo suyo y sin costos.
        $hasFullAccess = $user->hasAnyRole(['Admin', 'Ventas']);

        $filters = $request->only(['search', 'status', 'date_range']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $query = ServiceOrder::query()
            ->with([
                'client:id,name,branch_id',
                'technician:id,name,profile_photo_path',
                'salesRep:id,name,profile_photo_path'
            ])
            ->withCount('tasks')
            ->where('branch_id', $branchId);

        // 2. Filtrar órdenes si NO tiene acceso total
        if (!$hasFullAccess) {
            $query->where(function ($q) use ($user) {
                // Ver si es el técnico líder
                $q->where('technician_id', $user->id)
                  // O si está asignado a alguna tarea dentro de la orden
                  ->orWhereHas('tasks', function ($tq) use ($user) {
                      $tq->whereHas('assignees', function ($aq) use ($user) {
                          $aq->where('users.id', $user->id);
                      });
                  });
            });
        }

        $orders = $query
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere('installation_address', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($order) use ($hasFullAccess) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'client' => $order->client ? [
                        'id' => $order->client->id,
                        'name' => $order->client->name,
                    ] : null,
                    'installation_address' => $order->installation_address,
                    'start_date' => $order->start_date?->format('d/m/Y H:i'),
                    'technician' => $order->technician ? [
                        'name' => $order->technician->name,
                        'photo' => $order->technician->profile_photo_url, 
                    ] : null,
                    'sales_rep' => $order->salesRep ? [
                        'name' => $order->salesRep->name,
                        'photo' => $order->salesRep->profile_photo_url,
                    ] : null,
                    // 3. Ocultar el total si no tiene permisos (envía null)
                    'total_amount' => $hasFullAccess ? $order->total_amount : null,
                    'progress' => $order->progress ?? 0, 
                    'created_at_human' => $order->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('ServiceOrders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => ['Cotización', 'Aceptado', 'En Proceso', 'Completado', 'Facturado', 'Cancelado'],
            'can_view_financials' => $hasFullAccess // Nueva prop para el Index
        ]);
    }

    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        return Inertia::render('ServiceOrders/Create', [
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'technicians' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']), 
            'sales_reps' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'technician_id' => 'nullable|exists:users,id',
            'sales_rep_id' => 'required|exists:users,id',
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Completado,Facturado,Cancelado',
            'start_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'installation_address' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $validated['branch_id'] = $branchId;
        DB::transaction(function () use ($validated) {
            ServiceOrder::create($validated);
        });

        return redirect()->route('service-orders.index')->with('success', 'Orden de servicio generada correctamente.');
    }

    public function show(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');
        
        $user = Auth::user();

        // 1. Determinar Permisos Financieros
        $canViewFinancials = $user->hasAnyRole(['Admin', 'Ventas']);

        $serviceOrder->load([
            'client',
            'technician',
            'salesRep',
            'items.product', 
            'tasks.assignees', 
            'tasks.comments.user', 
            'media',
        ]);

        // 2. Generar URL Firmada (Seguridad anti-modificación de ID)
        // Esto genera una URL tipo: domain.com/service-orders/1?signature=abc1234...
        // Si alguien cambia el "1" por un "2", la firma no coincidirá.
        $serviceOrder->secure_url = URL::signedRoute('service-orders.show', ['serviceOrder' => $serviceOrder->id]);

        // 3. Ocultar datos sensibles si no tiene permisos
        if (!$canViewFinancials) {
            $serviceOrder->total_amount = 0;
            $serviceOrder->items->transform(function ($item) {
                $item->price = 0;
                if ($item->product) {
                    $item->product->sale_price = 0;
                    $item->product->purchase_price = 0;
                }
                return $item;
            });
        }

        $currentUserId = $user->id;

        $diagramData = $serviceOrder->tasks->map(function ($task) use ($currentUserId) {
            $hasUnread = $task->comments->contains(function ($comment) use ($currentUserId) {
                return $comment->user_id !== $currentUserId;
            });

            return [
                'id' => $task->id,
                'name' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'start' => $task->start_date?->format('Y-m-d H:i:s'),
                'finish_date' => $task->finish_date?->format('Y-m-d H:i:s'),
                'end' => $task->due_date?->format('Y-m-d H:i:s'),
                'status' => $task->status,
                'has_unread_comments' => $hasUnread,
                'comments' => $task->comments->map(fn($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'user' => $c->user->name,
                    'user_avatar' => $c->user->profile_photo_url,
                    'created_at' => $c->created_at->diffForHumans()
                ]),
                'assignees' => $task->assignees->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone, 
                    'avatar' => $user->profile_photo_url 
                ])
            ];
        });

        $assignableUsers = User::where('branch_id', $branchId)
            ->where('id', '!=', 1) // Ocultamos al usuario de soporte (ID 1)
            ->where('is_active', true)
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        $availableProducts = Product::where('category_id', '!=', null) 
            ->select('id', 'name', 'sku', 'sale_price', 'purchase_price') 
            ->orderBy('name')
            ->get();
        
        if (!$canViewFinancials) {
            $availableProducts->transform(function ($p) {
                $p->makeHidden(['sale_price', 'purchase_price']);
                return $p;
            });
        }

        return Inertia::render('ServiceOrders/Show', [
            'order' => $serviceOrder,
            'diagram_data' => $diagramData,
            'stats' => [
                'total_tasks' => $serviceOrder->tasks->count(),
                'completed_tasks' => $serviceOrder->tasks->where('status', 'Completado')->count(),
                'pending_balance' => 0 
            ],
            'assignable_users' => $assignableUsers,
            'available_products' => $availableProducts,
            'can_view_financials' => $canViewFinancials
        ]);
    }

    public function edit(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        return Inertia::render('ServiceOrders/Edit', [
            'order' => $serviceOrder,
            // AGREGA ESTAS LÍNEAS:
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']),
            // -------------------
            'technicians' => User::where('branch_id', $branchId)->where('id', '!=', 1)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id', // Faltaba este
            'sales_rep_id' => 'required|exists:users,id', // Faltaba este
            'technician_id' => 'nullable|exists:users,id',
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Completado,Facturado,Cancelado',
            'start_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0', // Faltaba este
            'installation_address' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $serviceOrder->update($validated);

        return redirect()->route('service-orders.show', $serviceOrder->id)->with('success', 'Orden actualizada.');
    }

    public function updateStatus(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        $validated = $request->validate([
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Completado,Facturado,Cancelado'
        ]);

        $newStatus = $validated['status'];
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'Completado') {
            $updateData['completion_date'] = now();
        } else {
            $updateData['completion_date'] = null;
        }

        $serviceOrder->update($updateData);
        return back()->with('success', "Estatus actualizado a {$newStatus}.");
    }

    // Método para subir evidencias (Media Library)
    public function uploadMedia(Request $request, ServiceOrder $serviceOrder)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        $serviceOrder->addMediaFromRequest('file')->toMediaCollection('evidences');

        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function addItems(Request $request, ServiceOrder $serviceOrder)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        DB::transaction(function () use ($serviceOrder, $product, $validated) {
            $serviceOrder->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'price' => $product->sale_price,
            ]);

            InventoryService::removeStock(
                product: $product,
                branchId: $serviceOrder->branch_id,
                quantity: $validated['quantity'],
                reason: 'Instalación',
                reference: $serviceOrder,
                notes: "Material asignado a Orden de Servicio #{$serviceOrder->id}"
            );
        });

        return back()->with('success', 'Producto asignado correctamente.');
    }

    public function removeItem($itemId)
    {
        $item = ServiceOrderItem::with(['product', 'serviceOrder'])->findOrFail($itemId);
        
        DB::transaction(function () use ($item) {
            InventoryService::addStock(
                product: $item->product,
                branchId: $item->serviceOrder->branch_id,
                quantity: $item->quantity,
                reason: 'Devolución',
                reference: $item->serviceOrder,
                notes: "Material removido de Orden de Servicio #{$item->serviceOrder->id}"
            );

            $item->delete();
        });

        return back()->with('success', 'Producto eliminado de la orden y stock devuelto.');
    }

    public function destroy(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        if ($serviceOrder->status === 'Completado' || $serviceOrder->status === 'Facturado') {
            return back()->with('error', 'No se puede eliminar una orden completada o facturada.');
        }

        $serviceOrder->delete(); 
        return redirect()->route('service-orders.index')->with('success', 'Orden eliminada.');
    }
}