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
use Illuminate\Validation\ValidationException;

class ServiceOrderController extends Controller
{
    /**
     * Muestra el listado de órdenes de servicio (Dashboard Operativo).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;
        
        $hasFullAccess = $user->hasAnyRole(['Admin', 'Ventas', 'Gestor']);

        // Agregamos system_type a los filtros
        $filters = $request->only(['search', 'status', 'municipality', 'state', 'date_range', 'system_type']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;
        $systemType = $filters['system_type'] ?? null;

        $availableMunicipalities = ServiceOrder::where('branch_id', $branchId)
            ->whereNotNull('installation_municipality')
            ->where('installation_municipality', '!=', '')
            ->distinct()
            ->orderBy('installation_municipality')
            ->pluck('installation_municipality');

        $availableStates = ServiceOrder::where('branch_id', $branchId)
            ->whereNotNull('installation_state')
            ->where('installation_state', '!=', '')
            ->distinct()
            ->orderBy('installation_state')
            ->pluck('installation_state');

        $query = ServiceOrder::query()
            ->with([
                'client:id,name,branch_id',
                'technician:id,name,profile_photo_path',
                'salesRep:id,name,profile_photo_path'
            ])
            ->withCount('tasks')
            ->where('branch_id', $branchId);

        if (!$hasFullAccess) {
            $query->where(function ($q) use ($user) {
                $q->where('technician_id', $user->id)
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
                      ->orWhere('service_number', 'like', "%{$search}%")
                      ->orWhere('meter_number', 'like', "%{$search}%") // Búsqueda por medidor
                      ->orWhere('installation_street', 'like', "%{$search}%")
                      ->orWhere('installation_neighborhood', 'like', "%{$search}%")
                      ->orWhere('installation_municipality', 'like', "%{$search}%") 
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($municipality, function ($query, $municipality) {
                $query->where('installation_municipality', $municipality);
            })
            ->when($state, function ($query, $state) {
                $query->where('installation_state', $state);
            })
            ->when($systemType, function ($query, $systemType) {
                $query->where('system_type', $systemType);
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
                    'service_number' => $order->service_number,
                    'meter_number' => $order->meter_number,
                    'rate_type' => $order->rate_type,
                    'system_type' => $order->system_type, // Agregado a la respuesta
                    'installation_address' => $order->full_installation_address, 
                    'municipality' => $order->installation_municipality,
                    'state' => $order->installation_state,
                    'start_date' => $order->start_date?->format('d/m/Y H:i'),
                    'technician' => $order->technician ? [
                        'name' => $order->technician->name,
                        'photo' => $order->technician->profile_photo_url, 
                    ] : null,
                    'sales_rep' => $order->salesRep ? [
                        'name' => $order->salesRep->name,
                        'photo' => $order->salesRep->profile_photo_url,
                    ] : null,
                    'total_amount' => $hasFullAccess ? $order->total_amount : null,
                    'progress' => $order->progress ?? 0, 
                    'created_at_human' => $order->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('ServiceOrders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => ['Cotización', 'Aceptado', 'En Proceso', 'Completado', 'Facturado', 'Cancelado'],
            'municipalities' => $availableMunicipalities,
            'states' => $availableStates,
            'can_view_financials' => $hasFullAccess
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
            
            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'system_type' => 'nullable|string|max:255', // Nuevo campo validado
            'meter_number' => 'nullable|string|max:255',

            'installation_street' => 'required|string|max:255',
            'installation_exterior_number' => 'nullable|string|max:50',
            'installation_interior_number' => 'nullable|string|max:50',
            'installation_neighborhood' => 'nullable|string|max:255',
            'installation_municipality' => 'nullable|string|max:255',
            'installation_state' => 'nullable|string|max:255',
            'installation_zip_code' => 'nullable|string|max:10',
            'installation_country' => 'nullable|string|max:100',
            
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
        $canViewFinancials = $user->hasAnyRole(['Admin']);

        $serviceOrder->load([
            'client',
            'technician',
            'salesRep',
            'items.product', 
            'tasks.assignees', 
            'tasks.comments.user', 
            'media',
        ]);

        $serviceOrder->secure_url = URL::signedRoute('service-orders.show', ['serviceOrder' => $serviceOrder->id]);

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
            ->where('id', '!=', 1) 
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
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']),
            'technicians' => User::where('branch_id', $branchId)->where('id', '!=', 1)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'sales_rep_id' => 'required|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Completado,Facturado,Cancelado',
            'start_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',

            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'system_type' => 'nullable|string|max:255', // Nuevo campo validado
            'meter_number' => 'nullable|string|max:255', 
            
            'installation_street' => 'required|string|max:255',
            'installation_exterior_number' => 'nullable|string|max:50',
            'installation_interior_number' => 'nullable|string|max:50',
            'installation_neighborhood' => 'nullable|string|max:255',
            'installation_municipality' => 'nullable|string|max:255',
            'installation_state' => 'nullable|string|max:255',
            'installation_zip_code' => 'nullable|string|max:10',
            'installation_country' => 'nullable|string|max:100',
            
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

    public function uploadMedia(Request $request, ServiceOrder $serviceOrder)
    {
        $request->validate([
            'file' => 'required|file|max:10240', 
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
        // Validar sucursal
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return inertia('Forbidden403');
        }

        // Validar si el estatus permite la eliminación
        if ($serviceOrder->status === 'Completado' || $serviceOrder->status === 'Facturado') {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar una orden que ya ha sido completada o facturada.'
            ]);
        }

        try {
            DB::transaction(function () use ($serviceOrder) {
                // Restaurar stock de los productos incluidos en la orden
                foreach ($serviceOrder->items as $item) {
                    if ($item->product) {
                        InventoryService::addStock(
                            product: $item->product,
                            branchId: $serviceOrder->branch_id,
                            quantity: $item->quantity,
                            reason: 'Cancelación Orden',
                            reference: $serviceOrder,
                            notes: "Eliminación de Orden de Servicio #{$serviceOrder->id}"
                        );
                    }
                }

                // Limpiar relaciones y eliminar
                $serviceOrder->payments()->delete(); 
                $serviceOrder->items()->delete();    
                $serviceOrder->delete(); 
            });

            return redirect()->route('service-orders.index')
                ->with('success', 'Orden eliminada y stock restaurado correctamente.');

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'delete' => 'Ocurrió un error inesperado al intentar eliminar la orden.'
            ]);
        }
    }
}