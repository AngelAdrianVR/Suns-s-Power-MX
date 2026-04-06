<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\EvidenceTemplate;
use App\Models\ServiceOrderEvidence;
use App\Models\SystemType;
use App\Models\User;
use App\Models\TaskTemplate; 
use App\Models\Ticket;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;
        
        $filters = $request->only(['search', 'status', 'municipality', 'state', 'date_range', 'system_type']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;
        $systemType = $filters['system_type'] ?? null;
        $dateRange = $filters['date_range'] ?? null;

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

        $orders = $query
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                    ->orWhere('service_number', 'like', "%{$search}%")
                    ->orWhere('meter_number', 'like', "%{$search}%")
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
            ->when($dateRange, function ($query, $dateRange) {
                if (is_array($dateRange) && count($dateRange) === 2) {
                    try {
                        $start = is_numeric($dateRange[0]) 
                            ? Carbon::createFromTimestampMs($dateRange[0])->startOfDay()
                            : Carbon::parse($dateRange[0])->startOfDay();
                            
                        $end = is_numeric($dateRange[1])
                            ? Carbon::createFromTimestampMs($dateRange[1])->endOfDay()
                            : Carbon::parse($dateRange[1])->endOfDay();

                        $query->whereBetween('created_at', [$start, $end]);
                    } catch (\Exception $e) {
                        // Ignorar filtro si hay error en el parseo de fechas
                    }
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($order) {
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
                    'system_type' => $order->system_type,
                    'installation_address' => $order->full_installation_address, 
                    'municipality' => $order->installation_municipality,
                    'state' => $order->installation_state,
                    'installation_lat' => $order->installation_lat,
                    'installation_lng' => $order->installation_lng,
                    'start_date' => $order->start_date?->format('d/m/Y H:i'),
                    'technician' => $order->technician ? [
                        'name' => $order->technician->name,
                        'photo' => $order->technician->profile_photo_url, 
                    ] : null,
                    'total_amount' => $order->total_amount,
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
            'can_view_financials' => $user->can('sales.view_sales_amount')
        ]);
    }

    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        return Inertia::render('ServiceOrders/Create', [
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'technicians' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']), 
            'sales_reps' => User::where('branch_id', $branchId)->where('id', '!=', 1)->where('is_active', true)->get(['id', 'name']),
            'system_types' => SystemType::where('branch_id', $branchId)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        $userId = Auth::id();
        
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'technician_id' => 'nullable|exists:users,id',
            'sales_rep_id' => 'required|exists:users,id',
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Completado,Facturado,Cancelado',
            'start_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'system_type' => 'nullable|string|max:255',
            'voltage' => 'nullable|in:110V,220V,440V',           
            'number_of_wires' => 'nullable|integer|in:1,2,3',    
            'number_of_units' => 'nullable|integer|min:0',       
            'unit_capacity' => 'nullable|numeric|min:0',         
            'total_capacity' => 'nullable|numeric|min:0',        
            'meter_number' => 'nullable|string|max:255',
            'installation_street' => 'required|string|max:255',
            'installation_exterior_number' => 'nullable|string|max:50',
            'installation_interior_number' => 'nullable|string|max:50',
            'installation_neighborhood' => 'nullable|string|max:255',
            'installation_municipality' => 'nullable|string|max:255',
            'installation_state' => 'nullable|string|max:255',
            'installation_zip_code' => 'nullable|string|max:10',
            'installation_country' => 'nullable|string|max:100',
            'installation_lat' => 'nullable|numeric',
            'installation_lng' => 'nullable|numeric',
            'notes' => 'nullable|string'
        ]);

        $validated['branch_id'] = $branchId;
        
        DB::transaction(function () use ($validated, $branchId, $userId) {
            $serviceOrder = ServiceOrder::create($validated);

            if (!empty($validated['system_type'])) {
                // 1. Evidencias Requeridas
                $evidenceTemplates = EvidenceTemplate::where('branch_id', $branchId)
                    ->where('system_type', $validated['system_type'])
                    ->orderBy('order', 'asc')
                    ->get();

                $evidenceMap = []; 
                foreach ($evidenceTemplates as $evTemplate) {
                    $ev = $serviceOrder->evidences()->create([
                        'title' => $evTemplate->title,
                        'description' => $evTemplate->description,
                        'allows_multiple' => $evTemplate->allows_multiple ?? false,
                        'order' => $evTemplate->order ?? 0, 
                    ]);
                    $evidenceMap[$evTemplate->id] = $ev->id;
                }

                // 2. Programar Tareas
                $templates = TaskTemplate::with(['users', 'evidenceTemplates'])
                    ->where('branch_id', $branchId)
                    ->where('system_type', $validated['system_type'])
                    ->orderBy('order', 'asc') // Aseguramos el orden de las plantillas
                    ->get();

                $taskOrderIndex = 1;
                foreach ($templates as $template) {
                    $recurringCount = $template->is_recurring ? ($template->recurring_count ?? 1) : 1;
                    $interval = $template->recurring_interval ?? 1;
                    $unit = $template->recurring_unit ?? 'months';

                    for ($i = 1; $i <= $recurringCount; $i++) {
                        $taskTitle = $template->title;
                        if ($recurringCount > 1) {
                            $taskTitle .= " ($i/$recurringCount)";
                        }

                        $startDays = $template->start_days ?? 0;
                        $durationDays = $template->duration_days ?? 1;

                        $startDate = now()->addDays($startDays)->startOfDay();

                        if ($i > 1 && $template->is_recurring) {
                            $multiplier = $i - 1;
                            if ($unit === 'days') $startDate->addDays($interval * $multiplier);
                            elseif ($unit === 'weeks') $startDate->addWeeks($interval * $multiplier);
                            elseif ($unit === 'months') $startDate->addMonths($interval * $multiplier);
                            elseif ($unit === 'years') $startDate->addYears($interval * $multiplier);
                        }

                        $dueDate = $startDate->copy()->addDays(max(0, $durationDays - 1))->endOfDay();

                        $task = $serviceOrder->tasks()->create([
                            'branch_id' => $branchId,
                            'title' => $taskTitle,
                            'description' => $template->description,
                            'priority' => $template->priority,
                            'status' => 'Pendiente',
                            'created_by' => $userId,
                            'start_date' => $startDate,  
                            'due_date' => $dueDate,
                            'is_recurring' => $template->is_recurring ?? false,
                            'recurring_interval' => $interval,
                            'recurring_unit' => $unit,
                            'order' => $taskOrderIndex, // Agregado: Guardamos el orden
                        ]);

                        $taskOrderIndex++;

                        $userIds = $template->users->pluck('id')->toArray();
                        if (!empty($userIds)) {
                            $task->assignees()->sync($userIds);
                        }

                        $requiredEvidenceIds = [];
                        foreach ($template->evidenceTemplates as $reqEvTpl) {
                            if (isset($evidenceMap[$reqEvTpl->id])) {
                                $requiredEvidenceIds[] = $evidenceMap[$reqEvTpl->id];
                            }
                        }
                        if (!empty($requiredEvidenceIds)) {
                            $task->requiredEvidences()->sync($requiredEvidenceIds);
                        }
                    }
                }

                // 3. Material Predeterminado
                $systemTypeModel = SystemType::with('products')
                    ->where('branch_id', $branchId)
                    ->where('name', $validated['system_type'])
                    ->first();

                if ($systemTypeModel) {
                    // Ordenar productos según el pivote (si existe el orden) o alfabéticamente
                    $products = $systemTypeModel->products->sortBy(fn($p) => $p->pivot->order ?? 0);
                    $productOrderIndex = 1;

                    foreach ($products as $product) {
                        $serviceOrder->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $product->pivot->quantity,
                            'price' => $product->sale_price,
                            'order' => $productOrderIndex, // Agregado: Guardamos el orden
                        ]);
                        $productOrderIndex++;

                        InventoryService::removeStock(
                            product: $product,
                            branchId: $branchId,
                            quantity: $product->pivot->quantity,
                            reason: 'Instalación (Auto)',
                            reference: $serviceOrder,
                            notes: "Material asignado automáticamente por tipo de sistema a Orden #{$serviceOrder->id}"
                        );
                    }
                }
            }
        });

        return redirect()->route('service-orders.index')->with('success', 'Orden de servicio y cronograma generados correctamente.');
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
            'items.product.category', 
            'tasks.assignees', 
            'tasks.comments.user', 
            'tasks.requiredEvidences.media',
            'media',
            'evidences' => function ($query) {
                $query->orderBy('order', 'asc')->orderBy('id', 'asc');
            },
            'evidences.media'
        ]);

        $serviceOrder->secure_url = URL::signedRoute('service-orders.show', ['serviceOrder' => $serviceOrder->id]);

        if (!$canViewFinancials) {
            $serviceOrder->total_amount = 0;
        }

        $serviceOrder->items->transform(function ($item) use ($canViewFinancials) {
            $item->quantity = (float) $item->quantity;
            if ($item->used_quantity !== null) {
                $item->used_quantity = (float) $item->used_quantity;
            }

            if (!$canViewFinancials) {
                $item->price = 0;
                if ($item->product) {
                    $item->product->sale_price = 0;
                    $item->product->purchase_price = 0;
                }
            }
            return $item;
        });

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
                'is_recurring' => $task->is_recurring,
                'recurring_interval' => $task->recurring_interval,
                'recurring_unit' => $task->recurring_unit,
                'order' => $task->order ?? 0, // Agregado: Retornamos el orden para Vue
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
                ]),
                'required_evidences' => $task->requiredEvidences->map(fn($ev) => [
                    'id' => $ev->id,
                    'title' => $ev->title,
                    'media' => $ev->media ?? []
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
            'system_types' => SystemType::where('branch_id', $branchId)->orderBy('name')->get(),
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
            'system_type' => 'nullable|string|max:255',
            'voltage' => 'nullable|in:110V,220V,440V',           
            'number_of_wires' => 'nullable|integer|in:1,2,3',    
            'number_of_units' => 'nullable|integer|min:0',       
            'unit_capacity' => 'nullable|numeric|min:0',         
            'total_capacity' => 'nullable|numeric|min:0',        
            'meter_number' => 'nullable|string|max:255', 
            'installation_street' => 'required|string|max:255',
            'installation_exterior_number' => 'nullable|string|max:50',
            'installation_interior_number' => 'nullable|string|max:50',
            'installation_neighborhood' => 'nullable|string|max:255',
            'installation_municipality' => 'nullable|string|max:255',
            'installation_state' => 'nullable|string|max:255',
            'installation_zip_code' => 'nullable|string|max:10',
            'installation_country' => 'nullable|string|max:100',
            'installation_lat' => 'nullable|numeric',
            'installation_lng' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $oldSystemType = $serviceOrder->system_type;

        DB::transaction(function () use ($serviceOrder, $validated, $oldSystemType, $branchId) {
            $serviceOrder->update($validated);

            if (isset($validated['system_type']) && $oldSystemType !== $validated['system_type']) {
                $serviceOrder->tasks()->where('status', 'Pendiente')->delete();
                $serviceOrder->evidences()->doesntHave('media')->delete();

                if (!empty($validated['system_type'])) {
                    
                    // Evidencias
                    $evidenceTemplates = EvidenceTemplate::where('branch_id', $branchId)
                        ->where('system_type', $validated['system_type'])
                        ->orderBy('order', 'asc')
                        ->get();

                    $evidenceMap = [];
                    foreach ($evidenceTemplates as $evTemplate) {
                        $ev = $serviceOrder->evidences()->create([
                            'title' => $evTemplate->title,
                            'description' => $evTemplate->description,
                            'allows_multiple' => $evTemplate->allows_multiple ?? false, 
                            'order' => $evTemplate->order ?? 0,
                        ]);
                        $evidenceMap[$evTemplate->id] = $ev->id;
                    }

                    // Tareas
                    $templates = TaskTemplate::with(['users', 'evidenceTemplates'])
                        ->where('branch_id', $branchId)
                        ->where('system_type', $validated['system_type'])
                        ->orderBy('order', 'asc') // Aseguramos el orden
                        ->get();

                    $userId = Auth::id();
                    $taskOrderIndex = 1;

                    foreach ($templates as $template) {
                        $recurringCount = $template->is_recurring ? ($template->recurring_count ?? 1) : 1;
                        $interval = $template->recurring_interval ?? 1;
                        $unit = $template->recurring_unit ?? 'months';

                        for ($i = 1; $i <= $recurringCount; $i++) {
                            $taskTitle = $template->title;
                            if ($recurringCount > 1) {
                                $taskTitle .= " ($i/$recurringCount)";
                            }

                            $startDays = $template->start_days ?? 0;
                            $durationDays = $template->duration_days ?? 1;

                            $startDate = now()->addDays($startDays)->startOfDay();

                            if ($i > 1 && $template->is_recurring) {
                                $multiplier = $i - 1;
                                if ($unit === 'days') $startDate->addDays($interval * $multiplier);
                                elseif ($unit === 'weeks') $startDate->addWeeks($interval * $multiplier);
                                elseif ($unit === 'months') $startDate->addMonths($interval * $multiplier);
                                elseif ($unit === 'years') $startDate->addYears($interval * $multiplier);
                            }

                            $dueDate = $startDate->copy()->addDays(max(0, $durationDays - 1))->endOfDay();

                            $task = $serviceOrder->tasks()->create([
                                'branch_id' => $branchId,
                                'title' => $taskTitle,
                                'description' => $template->description,
                                'priority' => $template->priority,
                                'status' => 'Pendiente',
                                'created_by' => $userId,
                                'start_date' => $startDate,  
                                'due_date' => $dueDate,
                                'is_recurring' => $template->is_recurring ?? false,
                                'recurring_interval' => $interval,
                                'recurring_unit' => $unit,
                                'order' => $taskOrderIndex, // Agregado: Guardamos el orden
                            ]);
                            $taskOrderIndex++;

                            $userIds = $template->users->pluck('id')->toArray();
                            if (!empty($userIds)) {
                                $task->assignees()->sync($userIds);
                            }

                            $requiredEvidenceIds = [];
                            foreach ($template->evidenceTemplates as $reqEvTpl) {
                                if (isset($evidenceMap[$reqEvTpl->id])) {
                                    $requiredEvidenceIds[] = $evidenceMap[$reqEvTpl->id];
                                }
                            }
                            if (!empty($requiredEvidenceIds)) {
                                $task->requiredEvidences()->sync($requiredEvidenceIds);
                            }
                        }
                    }

                    // Productos / Material Predeterminado
                    $systemTypeModel = SystemType::with('products')
                        ->where('branch_id', $branchId)
                        ->where('name', $validated['system_type'])
                        ->first();

                    if ($systemTypeModel) {
                        $products = $systemTypeModel->products->sortBy(fn($p) => $p->pivot->order ?? 0);
                        $productOrderIndex = 1;

                        foreach ($products as $product) {
                            $serviceOrder->items()->create([
                                'product_id' => $product->id,
                                'quantity' => $product->pivot->quantity,
                                'price' => $product->sale_price,
                                'order' => $productOrderIndex, // Agregado: Guardamos el orden
                            ]);
                            $productOrderIndex++;

                            InventoryService::removeStock(
                                product: $product,
                                branchId: $branchId,
                                quantity: $product->pivot->quantity,
                                reason: 'Instalación (Auto)',
                                reference: $serviceOrder,
                                notes: "Material asignado automáticamente por edición de tipo de sistema a Orden #{$serviceOrder->id}"
                            );
                        }
                    }
                }
            }
        });

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

        if ($newStatus === 'Completado') {
            $incompleteTasks = $serviceOrder->tasks()->where('status', '!=', 'Completado')->count();
            if ($incompleteTasks > 0) {
                return back()->with('error', 'No se puede completar la orden: Tareas pendientes de finalizar.');
            }

            $unreportedCount = $serviceOrder->items()->whereNull('used_quantity')->count();
            if ($unreportedCount > 0) {
                return back()->with('error', 'No se puede completar la orden: Faltan materiales por conciliar.');
            }
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'Completado') {
            $updateData['completion_date'] = now();
        } else {
            $updateData['completion_date'] = null;
        }

        $serviceOrder->update($updateData);
        return back()->with('success', "Estatus actualizado a {$newStatus}.");
    }

    public function confirmInstallation(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return inertia('Forbidden403');

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required|exists:service_order_items,id',
            'items.*.used_quantity' => 'required|numeric|min:0',
            'installation_notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($serviceOrder, $validated) {
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $serviceOrder->items()->where('id', $itemData['id'])->update([
                        'used_quantity' => $itemData['used_quantity']
                    ]);
                }
            }

            $newNotes = $serviceOrder->notes;
            if (!empty($validated['installation_notes'])) {
                $newNotes .= "\n\n--- Reporte de Materiales (Instalación) ---\n" . $validated['installation_notes'];
                $serviceOrder->update(['notes' => $newNotes]);
            }

            $incompleteTasks = $serviceOrder->tasks()->where('status', '!=', 'Completado')->count();
            if ($incompleteTasks === 0 && !in_array($serviceOrder->status, ['Completado', 'Facturado', 'Cancelado'])) {
                $serviceOrder->update([
                    'status' => 'Completado',
                    'completion_date' => now(),
                    'inventory_reconciled' => false 
                ]);
            }
        });

        return back()->with('success', 'Cantidades de material conciliadas y guardadas correctamente.');
    }

    public function addItems(Request $request, ServiceOrder $serviceOrder)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        DB::transaction(function () use ($serviceOrder, $product, $validated) {
            // Asignar el último orden
            $lastOrder = $serviceOrder->items()->max('order') ?? 0;

            $serviceOrder->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'price' => $product->sale_price,
                'order' => $lastOrder + 1,
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
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar una orden que ya ha sido completada o facturada.'
            ]);
        }

        try {
            DB::transaction(function () use ($serviceOrder) {
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

                foreach ($serviceOrder->evidences as $evidence) {
                    $evidence->clearMediaCollection('specific_evidences');
                    $evidence->delete();
                }

                $serviceOrder->clearMediaCollection('evidences');

                $serviceOrder->tasks()->delete();
                $serviceOrder->payments()->delete(); 
                $serviceOrder->items()->delete();
                $serviceOrder->documents()->delete();
                
                if ($serviceOrder->contract) {
                    $serviceOrder->contract()->delete();
                }

                $ticketIds = Ticket::where('related_service_order_id', $serviceOrder->id)->pluck('id');
                if ($ticketIds->isNotEmpty()) {
                    \App\Models\Task::where('taskable_type', Ticket::class)
                        ->whereIn('taskable_id', $ticketIds)
                        ->delete();
                        
                    Ticket::whereIn('id', $ticketIds)->delete();
                }

                $serviceOrder->delete(); 
            });
            return redirect()->route('service-orders.index')->with('success', 'Orden eliminada y stock restaurado correctamente.');
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['delete' => 'Ocurrió un error al intentar eliminar la orden.']);
        }
    }

    public function uploadMedia(Request $request, ServiceOrder $serviceOrder)
    {
        $request->validate(['file' => 'required|file|max:10240']);
        $serviceOrder->addMediaFromRequest('file')->toMediaCollection('evidences');
        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function uploadEvidenceMedia(Request $request, ServiceOrderEvidence $evidence)
    {
        $request->validate([
            'file' => 'nullable|file|max:10240',
            'files.*' => 'nullable|file|max:10240',
            'comment' => 'nullable|string' 
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $evidence->addMedia($file)->toMediaCollection('specific_evidences');
            }
        } elseif ($request->hasFile('file')) {
            $evidence->addMediaFromRequest('file')->toMediaCollection('specific_evidences');
        }

        if ($request->has('comment')) {
            $evidence->update(['comment' => $request->comment]);
        }

        return back()->with('success', 'Evidencia actualizada correctamente.');
    }

    /**
     * Sincroniza y genera las evidencias, tareas y productos faltantes/ordenados
     */
    public static function syncSystemTypeData($branchId = null)
    {
        $query = ServiceOrder::whereNotIn('status', ['Completado', 'Facturado', 'Cancelado'])
            ->whereNotNull('system_type');
            
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        
        $orders = $query->get();

        foreach ($orders as $order) {
            $systemTypeModel = SystemType::with('products')->where('branch_id', $order->branch_id)->where('name', $order->system_type)->first();
            
            // 1. Sync Products (Items) + Update Order
            if ($systemTypeModel) {
                $existingProducts = $order->items()->get()->keyBy('product_id');
                // Ordenamos basado en lo que mandó el pivote
                $products = $systemTypeModel->products->sortBy(fn($p) => $p->pivot->order ?? 0);
                $productOrderIndex = 1;

                foreach ($products as $product) {
                    if (!$existingProducts->has($product->id)) {
                        $order->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $product->pivot->quantity,
                            'price' => $product->sale_price,
                            'order' => $productOrderIndex, // Se le asigna el nuevo orden
                        ]);
                        InventoryService::removeStock(
                            product: $product,
                            branchId: $order->branch_id,
                            quantity: $product->pivot->quantity,
                            reason: 'Instalación (Auto-Sync)',
                            reference: $order,
                            notes: "Material auto-asignado por sincronización de Tipo de Sistema a Orden #{$order->id}"
                        );
                    } else {
                        // Sincronizar orden y cantidades
                        $existingItem = $existingProducts->get($product->id);
                        $updateData = ['order' => $productOrderIndex]; // Forzamos el update del orden
                        
                        if ($existingItem->used_quantity === null && $existingItem->quantity != $product->pivot->quantity) {
                            $diff = $product->pivot->quantity - $existingItem->quantity;
                            $updateData['quantity'] = $product->pivot->quantity;
                            
                            if ($diff > 0) {
                                InventoryService::removeStock($product, $order->branch_id, $diff, 'Instalación (Auto-Sync)', $order, "Ajuste de material por actualización en plantilla");
                            } else {
                                InventoryService::addStock($product, $order->branch_id, abs($diff), 'Devolución (Auto-Sync)', $order, "Ajuste de material por actualización en plantilla");
                            }
                        }
                        $existingItem->update($updateData);
                    }
                    $productOrderIndex++;
                }
            }

            // 2. Sync Evidences
            $evidenceTemplates = EvidenceTemplate::where('branch_id', $order->branch_id)
                ->where('system_type', $order->system_type)
                ->orderBy('order', 'asc')
                ->get();
                
            $evidenceMap = []; 
            
            foreach ($evidenceTemplates as $evTemplate) {
                $ev = $order->evidences()->where('title', $evTemplate->title)->first();
                if (!$ev) {
                    $ev = $order->evidences()->create([
                        'title' => $evTemplate->title,
                        'description' => $evTemplate->description,
                        'allows_multiple' => $evTemplate->allows_multiple ?? false,
                        'order' => $evTemplate->order ?? 0,
                    ]);
                } else {
                    $ev->update([
                        'description' => $evTemplate->description,
                        'allows_multiple' => $evTemplate->allows_multiple ?? false,
                        'order' => $evTemplate->order ?? 0,
                    ]);
                }
                $evidenceMap[$evTemplate->id] = $ev->id;
            }
            
            // 3. Sync Tasks + Update Order
            $taskTemplates = TaskTemplate::with(['users', 'evidenceTemplates'])
                ->where('branch_id', $order->branch_id)
                ->where('system_type', $order->system_type)
                ->orderBy('order', 'asc') // Aseguramos el orden
                ->get();
                
            $taskOrderIndex = 1;
            
            foreach ($taskTemplates as $template) {
                $recurringCount = $template->is_recurring ? ($template->recurring_count ?? 1) : 1;
                $interval = $template->recurring_interval ?? 1;
                $unit = $template->recurring_unit ?? 'months';

                for ($i = 1; $i <= $recurringCount; $i++) {
                    $taskTitle = $template->title;
                    if ($recurringCount > 1) {
                        $taskTitle .= " ($i/$recurringCount)"; 
                    }

                    $task = $order->tasks()->where('title', $taskTitle)->first();
                    
                    if (!$task) {
                        $startDays = $template->start_days ?? 0;
                        $durationDays = $template->duration_days ?? 1;

                        $startDate = $order->created_at->copy()->addDays($startDays);

                        if ($i > 1 && $template->is_recurring) {
                            $multiplier = $i - 1;
                            if ($unit === 'days') $startDate->addDays($interval * $multiplier);
                            elseif ($unit === 'weeks') $startDate->addWeeks($interval * $multiplier);
                            elseif ($unit === 'months') $startDate->addMonths($interval * $multiplier);
                            elseif ($unit === 'years') $startDate->addYears($interval * $multiplier);
                        }

                        $startDate = $startDate->startOfDay();
                        $dueDate = $startDate->copy()->addDays(max(0, $durationDays - 1))->endOfDay();

                        $task = $order->tasks()->create([
                            'branch_id' => $order->branch_id,
                            'title' => $taskTitle,
                            'description' => $template->description,
                            'priority' => $template->priority,
                            'status' => 'Pendiente',
                            'created_by' => $order->sales_rep_id ?? 1, 
                            'start_date' => $startDate,  
                            'due_date' => $dueDate,
                            'is_recurring' => $template->is_recurring ?? false,
                            'recurring_interval' => $interval,
                            'recurring_unit' => $unit,
                            'order' => $taskOrderIndex, // Guardamos el nuevo orden sincronizado
                        ]);

                        $userIds = $template->users->pluck('id')->toArray();
                        if (!empty($userIds)) {
                            $task->assignees()->sync($userIds);
                        }
                    } else {
                        // Actualizamos el orden general que viene de la plantilla
                        $task->update([
                            'description' => $template->description,
                            'priority' => $template->priority,
                            'order' => $taskOrderIndex, // Actualizamos el orden
                        ]);
                    }
                    
                    $taskOrderIndex++;
                    
                    // Siempre sincronizar las evidencias por si fueron movidas/añadidas/quitadas
                    $requiredEvidenceIds = [];
                    foreach ($template->evidenceTemplates as $reqEvTpl) {
                        if (isset($evidenceMap[$reqEvTpl->id])) {
                            $requiredEvidenceIds[] = $evidenceMap[$reqEvTpl->id];
                        }
                    }
                    if (!empty($requiredEvidenceIds)) {
                        $task->requiredEvidences()->sync($requiredEvidenceIds);
                    } else {
                        $task->requiredEvidences()->detach();
                    }
                }
            }
        }
    }

    public function syncEvidences(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        self::syncSystemTypeData($branchId);

        return back()->with('success', "Sincronización de tareas, evidencias y productos completada.");
    }
}