<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderConditioning;
use App\Models\Product;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentInstallment;
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
use Illuminate\Support\Facades\Mail;
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
        
        // <-- SE AGREGÓ 'scheduled_date_range' A LOS FILTROS
        $filters = $request->only(['search', 'status', 'municipality', 'state', 'date_range', 'scheduled_date_range', 'system_type']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;
        $systemType = $filters['system_type'] ?? null;
        $dateRange = $filters['date_range'] ?? null;
        $scheduledDateRange = $filters['scheduled_date_range'] ?? null; // <-- NUEVO

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
                        // Ignorar filtro
                    }
                }
            })
            // <-- NUEVA LÓGICA DE FILTRADO POR FECHA PROGRAMADA (start_date)
            ->when($scheduledDateRange, function ($query, $scheduledDateRange) {
                if (is_array($scheduledDateRange) && count($scheduledDateRange) === 2) {
                    try {
                        $start = is_numeric($scheduledDateRange[0]) 
                            ? Carbon::createFromTimestampMs($scheduledDateRange[0])->startOfDay()
                            : Carbon::parse($scheduledDateRange[0])->startOfDay();
                            
                        $end = is_numeric($scheduledDateRange[1])
                            ? Carbon::createFromTimestampMs($scheduledDateRange[1])->endOfDay()
                            : Carbon::parse($scheduledDateRange[1])->endOfDay();

                        $query->whereBetween('start_date', [$start, $end]);
                    } catch (\Exception $e) {
                        // Ignorar filtro
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

        return Inertia::render('ServiceOrders/Index', [ // Corregido IndexOrder según el nombre de tu vista Vue
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
            'notes' => 'nullable|string',
            // Propuesta comercial
            'payment_method' => 'nullable|in:Contado,3 MSI,6 MSI,9 MSI,12 MSI,Personalizado',
            'down_payment' => 'nullable|numeric|min:0',
            'price_per_module' => 'nullable|numeric|min:0',
            'requires_pre_installation' => 'boolean',
            'pre_installation_details' => 'nullable|string',
            'pre_installation_assigned_to' => 'nullable|in:Sun\'s power mx,Cliente,Otro',
            // Acondicionamiento previo: listado de tareas
            'conditionings' => 'nullable|array',
            'conditionings.*.category' => 'required|in:Instalación Eléctrica,Área de Instalación',
            'conditionings.*.task' => 'required|string|max:255',
            'conditionings.*.user_id' => 'nullable|exists:users,id',
            'conditionings.*.notes' => 'nullable|string',
        ]);

        $validated['branch_id'] = $branchId;
        
        DB::transaction(function () use ($validated, $branchId, $userId) {
            $conditionings = $validated['conditionings'] ?? [];
            $serviceOrder = ServiceOrder::create(collect($validated)->except(['conditionings'])->toArray());

            // Guardar tareas de acondicionamiento previo
            foreach ($conditionings as $cond) {
                $serviceOrder->conditionings()->create([
                    'category' => $cond['category'],
                    'task' => $cond['task'],
                    'user_id' => $cond['user_id'] ?? null,
                    'status' => 'Pendiente',
                    'notes' => $cond['notes'] ?? null,
                ]);
            }

            // Crear pago de anticipo si aplica
            $downPayment = $validated['down_payment'] ?? null;
            if ($downPayment && $downPayment > 0) {
                Payment::create([
                    'branch_id' => $branchId,
                    'client_id' => $validated['client_id'],
                    'service_order_id' => $serviceOrder->id,
                    'amount' => $downPayment,
                    'payment_date' => now(),
                    'method' => 'Transferencia',
                    'notes' => 'Anticipo',
                ]);
            }

            // Generar cuotas proyectadas (payment_installments) según el plan de pago
            $serviceOrder->generateInstallments();

            if (!empty($validated['system_type'])) {
                // 1. Evidencias
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

                // 2. Tareas
                $templates = TaskTemplate::with(['users', 'evidenceTemplates'])
                    ->where('branch_id', $branchId)
                    ->where('system_type', $validated['system_type'])
                    ->orderBy('order', 'asc')
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

                        // Tarea Base (1era) es Normal, las subsecuentes son cíclicas
                        $isRecurringInstance = ($i > 1 && $template->is_recurring);

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
                            'is_recurring' => $isRecurringInstance,
                            'recurring_interval' => $interval,
                            'recurring_unit' => $unit,
                            'order' => $taskOrderIndex++,
                        ]);

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
                $systemTypeModel = SystemType::where('branch_id', $branchId)
                    ->where('name', $validated['system_type'])
                    ->first();

                if ($systemTypeModel) {
                    $products = $systemTypeModel->products()->withPivot(['quantity', 'order'])->orderByPivot('order', 'asc')->get();
                    $productOrderIndex = 1;

                    foreach ($products as $product) {
                        $serviceOrder->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $product->pivot->quantity,
                            'price' => $product->sale_price,
                            'order' => $productOrderIndex++,
                        ]);

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
            'items' => fn($q) => $q->with('product.category')->orderBy('order', 'asc'), 
            'tasks' => fn($q) => $q->with(['assignees', 'comments.user', 'requiredEvidences.media'])->orderBy('order', 'asc'),
            'evidences' => fn($q) => $q->with('media')->orderBy('order', 'asc'),
            'conditionings' => fn($q) => $q->with(['media', 'user'])->orderBy('id', 'asc'),
            'media'
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
                'order' => $task->order ?? 0,
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

        $serviceOrder->load(['conditionings.media']);

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
            // Propuesta comercial
            'payment_method' => 'nullable|in:Contado,3 MSI,6 MSI,9 MSI,12 MSI,Personalizado',
            'down_payment' => 'nullable|numeric|min:0',
            'price_per_module' => 'nullable|numeric|min:0',
            'requires_pre_installation' => 'boolean',
            'pre_installation_details' => 'nullable|string',
            'pre_installation_assigned_to' => 'nullable|in:Sun\'s power mx,Cliente,Otro',
            // Acondicionamiento previo: listado de tareas
            'conditionings' => 'nullable|array',
            'conditionings.*.category' => 'required|in:Instalación Eléctrica,Área de Instalación',
            'conditionings.*.task' => 'required|string|max:255',
            'conditionings.*.user_id' => 'nullable|exists:users,id',
            'conditionings.*.notes' => 'nullable|string',
        ]);

        $oldSystemType = $serviceOrder->system_type;

        DB::transaction(function () use ($serviceOrder, $validated, $oldSystemType, $branchId) {
            $conditionings = $validated['conditionings'] ?? [];
            $serviceOrder->update(collect($validated)->except(['conditionings'])->toArray());

            // Sincronizar tareas de acondicionamiento previo: borrar existentes y recrear
            $serviceOrder->conditionings()->delete();
            foreach ($conditionings as $cond) {
                $serviceOrder->conditionings()->create([
                    'category' => $cond['category'],
                    'task' => $cond['task'],
                    'user_id' => $cond['user_id'] ?? null,
                    'status' => 'Pendiente',
                    'notes' => $cond['notes'] ?? null,
                ]);
            }

            // Sincronizar pago de anticipo: eliminar anterior y crear nuevo si aplica
            $serviceOrder->payments()->where('notes', 'Anticipo')->delete();
            $downPayment = $validated['down_payment'] ?? null;
            if ($downPayment && $downPayment > 0) {
                Payment::create([
                    'branch_id' => $branchId,
                    'client_id' => $validated['client_id'],
                    'service_order_id' => $serviceOrder->id,
                    'amount' => $downPayment,
                    'payment_date' => now(),
                    'method' => 'Transferencia',
                    'notes' => 'Anticipo',
                ]);
            }

            // Regenerar cuotas proyectadas si cambió el método de pago, total o anticipo
            $serviceOrder->generateInstallments();

            if (isset($validated['system_type']) && $oldSystemType !== $validated['system_type']) {
                $serviceOrder->tasks()->where('status', 'Pendiente')->delete();
                $serviceOrder->evidences()->doesntHave('media')->delete();

                if (!empty($validated['system_type'])) {
                    
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

                    $templates = TaskTemplate::with(['users', 'evidenceTemplates'])
                        ->where('branch_id', $branchId)
                        ->where('system_type', $validated['system_type'])
                        ->orderBy('order', 'asc')
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

                            // Tarea Base (1era) es Normal, las subsecuentes son cíclicas
                            $isRecurringInstance = ($i > 1 && $template->is_recurring);

                            $startDate = now()->addDays($template->start_days ?? 0)->startOfDay();

                            if ($i > 1 && $template->is_recurring) {
                                $multiplier = $i - 1;
                                if ($unit === 'days') $startDate->addDays($interval * $multiplier);
                                elseif ($unit === 'weeks') $startDate->addWeeks($interval * $multiplier);
                                elseif ($unit === 'months') $startDate->addMonths($interval * $multiplier);
                                elseif ($unit === 'years') $startDate->addYears($interval * $multiplier);
                            }

                            $dueDate = $startDate->copy()->addDays(max(0, ($template->duration_days ?? 1) - 1))->endOfDay();

                            $task = $serviceOrder->tasks()->create([
                                'branch_id' => $branchId,
                                'title' => $taskTitle,
                                'description' => $template->description,
                                'priority' => $template->priority,
                                'status' => 'Pendiente',
                                'created_by' => $userId,
                                'start_date' => $startDate,  
                                'due_date' => $dueDate,
                                'is_recurring' => $isRecurringInstance,
                                'recurring_interval' => $interval,
                                'recurring_unit' => $unit,
                                'order' => $taskOrderIndex++,
                            ]);

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
                    $systemTypeModel = SystemType::where('branch_id', $branchId)
                        ->where('name', $validated['system_type'])
                        ->first();

                    if ($systemTypeModel) {
                        $products = $systemTypeModel->products()->withPivot(['quantity', 'order'])->orderByPivot('order', 'asc')->get();
                        $productOrderIndex = 1;

                        foreach ($products as $product) {
                            $serviceOrder->items()->create([
                                'product_id' => $product->id,
                                'quantity' => $product->pivot->quantity,
                                'price' => $product->sale_price,
                                'order' => $productOrderIndex++,
                            ]);

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

    // ---------------------------------------------------------------------------------
    // SINCRONIZADOR DEFINITIVO DE TAREAS, PRODUCTOS Y EVIDENCIAS
    // ---------------------------------------------------------------------------------
    public static function syncSystemTypeData($branchId = null)
    {
        // Se añade with('tasks') para que podamos manipular eficientemente las tareas existentes de la orden.
        $query = ServiceOrder::with('tasks')->whereNotIn('status', ['Completado', 'Facturado', 'Cancelado'])
            ->whereNotNull('system_type');
            
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        
        $orders = $query->get();

        foreach ($orders as $order) {
            $systemTypeModel = SystemType::where('branch_id', $order->branch_id)->where('name', $order->system_type)->first();
            
            if (!$systemTypeModel) continue;

            // =========================================================
            // 1. SINCRONIZAR EVIDENCIAS (Agregar faltantes, reordenar y eliminar sobrantes)
            // =========================================================
            $evidenceTemplates = EvidenceTemplate::where('branch_id', $order->branch_id)
                ->where('system_type', $order->system_type)
                ->orderBy('order', 'asc')
                ->get();
                
            $evidenceMap = []; 
            $activeEvidenceTitles = [];
            $evOrderIndex = 1;
            
            foreach ($evidenceTemplates as $evTemplate) {
                $activeEvidenceTitles[] = $evTemplate->title;
                $ev = $order->evidences()->where('title', $evTemplate->title)->first();
                
                if (!$ev) {
                    $ev = $order->evidences()->create([
                        'title' => $evTemplate->title,
                        'description' => $evTemplate->description,
                        'allows_multiple' => $evTemplate->allows_multiple ?? false,
                        'order' => $evOrderIndex,
                    ]);
                } else {
                    $ev->update([
                        'description' => $evTemplate->description,
                        'allows_multiple' => $evTemplate->allows_multiple ?? false,
                        'order' => $evOrderIndex,
                    ]);
                }
                $evidenceMap[$evTemplate->id] = $ev->id;
                $evOrderIndex++;
            }
            
            $order->evidences()->whereNotIn('title', $activeEvidenceTitles)->doesntHave('media')->delete();

            // =========================================================
            // 2. SINCRONIZAR PRODUCTOS (Agregar faltantes, reordenar y devolver sobrantes)
            // =========================================================
            $existingProducts = $order->items()->get()->keyBy('product_id');
            $products = $systemTypeModel->products()->withPivot(['quantity', 'order'])->orderByPivot('order', 'asc')->get();
            $productOrderIndex = 1;
            $activeProductIds = [];

            foreach ($products as $product) {
                $activeProductIds[] = $product->id;
                if (!$existingProducts->has($product->id)) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $product->pivot->quantity,
                        'price' => $product->sale_price,
                        'order' => $productOrderIndex, 
                    ]);
                    InventoryService::removeStock(
                        product: $product,
                        branchId: $order->branch_id,
                        quantity: $product->pivot->quantity,
                        reason: 'Instalación (Auto-Sync)',
                        reference: $order,
                        notes: "Material auto-asignado por sincronización de plantilla a Orden #{$order->id}"
                    );
                } else {
                    $existingItem = $existingProducts->get($product->id);
                    $updateData = ['order' => $productOrderIndex]; 
                    
                    if ($existingItem->used_quantity === null && $existingItem->quantity != $product->pivot->quantity) {
                        $diff = $product->pivot->quantity - $existingItem->quantity;
                        $updateData['quantity'] = $product->pivot->quantity;
                        
                        if ($diff > 0) {
                            InventoryService::removeStock($product, $order->branch_id, $diff, 'Instalación (Auto-Sync)', $order, "Ajuste de material (aumento) por plantilla");
                        } else {
                            InventoryService::addStock($product, $order->branch_id, abs($diff), 'Devolución (Auto-Sync)', $order, "Ajuste de material (resta) por plantilla");
                        }
                    }
                    $existingItem->update($updateData);
                }
                $productOrderIndex++;
            }
            
            foreach ($existingProducts as $item) {
                if (!in_array($item->product_id, $activeProductIds) && $item->used_quantity === null) {
                    InventoryService::addStock(
                        product: $item->product, 
                        branchId: $order->branch_id, 
                        quantity: $item->quantity, 
                        reason: 'Devolución (Auto-Sync)', 
                        reference: $order, 
                        notes: "Material retirado por eliminación en plantilla base."
                    );
                    $item->delete();
                }
            }


            // =========================================================
            // 3. SINCRONIZAR TAREAS (Agregar, Reordenar e Identificar Mantenimientos)
            // =========================================================
            $taskTemplates = TaskTemplate::with(['users', 'evidenceTemplates'])
                ->where('branch_id', $order->branch_id)
                ->where('system_type', $order->system_type)
                ->orderBy('order', 'asc') 
                ->get();
                
            $taskOrderIndex = 1;
            $activeTaskIds = [];
            $existingTasks = $order->tasks; 
            
            foreach ($taskTemplates as $template) {
                $baseTitle = $template->title;
                $recurringCount = $template->is_recurring ? ($template->recurring_count ?? 1) : 1;
                $interval = $template->recurring_interval ?? 1;
                $unit = $template->recurring_unit ?? 'months';

                // Buscar tareas existentes en esta orden cuyo título base coincida (ignorando el sufijo "(1/3)")
                // Esto nos permite reconectar las tareas viejas al template incluso si el contador cambió.
                $matchingExistingTasks = $existingTasks->filter(function($t) use ($baseTitle) {
                    $tBase = preg_replace('/\s\(\d+\/\d+\)$/', '', $t->title);
                    return $tBase === $baseTitle;
                })->values();

                for ($i = 1; $i <= $recurringCount; $i++) {
                    $taskTitle = $baseTitle;
                    if ($recurringCount > 1) {
                        $taskTitle .= " ($i/$recurringCount)";
                    }

                    // Tarea Base (1era) es Normal, las subsecuentes son cíclicas (mantenimiento)
                    $isRecurringInstance = ($i > 1 && $template->is_recurring);

                    // 1. Buscar coincidencia exacta del título
                    $task = $matchingExistingTasks->firstWhere('title', $taskTitle);
                    
                    // 2. Si no hay coincidencia exacta pero hay tareas que compartían la base, las reutilizamos
                    if (!$task && $matchingExistingTasks->count() > 0) {
                        $task = $matchingExistingTasks->shift(); 
                    } else if ($task) {
                        $matchingExistingTasks = $matchingExistingTasks->reject(fn($t) => $t->id === $task->id)->values();
                    }
                    
                    if (!$task) {
                        // Crear Tarea Nueva
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
                            'is_recurring' => $isRecurringInstance,
                            'recurring_interval' => $interval,
                            'recurring_unit' => $unit,
                            'order' => $taskOrderIndex, 
                        ]);

                        $userIds = $template->users->pluck('id')->toArray();
                        if (!empty($userIds)) {
                            $task->assignees()->sync($userIds);
                        }
                    } else {
                        // Actualizar Tarea Existente (asegura actualizar el orden dinámico)
                        $task->update([
                            'title' => $taskTitle, 
                            'description' => $template->description,
                            'priority' => $template->priority,
                            'is_recurring' => $isRecurringInstance,
                            'recurring_interval' => $interval,
                            'recurring_unit' => $unit,
                            'order' => $taskOrderIndex, 
                        ]);
                    }
                    
                    $activeTaskIds[] = $task->id;
                    $taskOrderIndex++;
                    
                    // Asegurar conexiones de Evidencias Requeridas
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
            
            // ELIMINACIÓN INTELIGENTE:
            // Borrar únicamente las tareas "Pendientes" cuyo título base pertenezca a algún template activo,
            // pero que no hayan sido usadas en la iteración actual (ej. si reduciste el contador de ciclos a 1, borra la 2 y 3).
            // Esto asegura que tareas "manuales" que el personal crea, no sean eliminadas accidentalmente.
            $allBaseTitles = $taskTemplates->pluck('title')->toArray();
            
            $order->tasks()->whereNotIn('id', $activeTaskIds)
                  ->where('status', 'Pendiente')
                  ->get()
                  ->each(function($t) use ($allBaseTitles) {
                       $tBase = preg_replace('/\s\(\d+\/\d+\)$/', '', $t->title);
                       if (in_array($tBase, $allBaseTitles)) {
                           $t->delete();
                       }
                  });
        }
    }

    public function syncEvidences(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        self::syncSystemTypeData($branchId);

        return back()->with('success', "Sincronización de tareas, evidencias y productos completada en base a las plantillas.");
    }

    // ================================================================
    // CRUD DE ACONDICIONAMIENTO PREVIO (Conditionings)
    // ================================================================

    public function storeConditioning(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) return back()->with('error', 'No autorizado.');

        $validated = $request->validate([
            'category' => 'required|in:Instalación Eléctrica,Área de Instalación',
            'task' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $serviceOrder->conditionings()->create([
            'category' => $validated['category'],
            'task' => $validated['task'],
            'user_id' => $validated['user_id'] ?? null,
            'status' => 'Pendiente',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Tarea de acondicionamiento agregada.');
    }

    public function updateConditioning(Request $request, ServiceOrderConditioning $conditioning)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($conditioning->serviceOrder->branch_id !== $branchId) return back()->with('error', 'No autorizado.');

        $validated = $request->validate([
            'category' => 'nullable|in:Instalación Eléctrica,Área de Instalación',
            'task' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:Pendiente,En proceso,Terminado',
            'notes' => 'nullable|string',
        ]);

        $conditioning->update(array_filter($validated, fn($v) => $v !== null));

        return back()->with('success', 'Tarea de acondicionamiento actualizada.');
    }

    public function destroyConditioning(ServiceOrderConditioning $conditioning)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($conditioning->serviceOrder->branch_id !== $branchId) return back()->with('error', 'No autorizado.');

        $conditioning->delete();

        return back()->with('success', 'Tarea de acondicionamiento eliminada.');
    }

    public function uploadConditioningMedia(Request $request, ServiceOrderConditioning $conditioning)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($conditioning->serviceOrder->branch_id !== $branchId) return back()->with('error', 'No autorizado.');

        $request->validate([
            'file' => 'required|file|max:81920', // 80 MB
        ]);

        // Limitar a 3 imágenes por tarea
        if ($conditioning->getMedia('evidence')->count() >= 3) {
            return back()->with('error', 'Máximo 3 evidencias por tarea de acondicionamiento.');
        }

        $conditioning->addMediaFromRequest('file')->toMediaCollection('evidence');

        return back()->with('success', 'Evidencia subida correctamente.');
    }

    public function deleteConditioningMedia(ServiceOrderConditioning $conditioning, $mediaId)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($conditioning->serviceOrder->branch_id !== $branchId) return back()->with('error', 'No autorizado.');

        $media = $conditioning->getMedia('evidence')->where('id', $mediaId)->first();
        if ($media) {
            $media->delete();
            return back()->with('success', 'Evidencia eliminada.');
        }

        return back()->with('error', 'Archivo no encontrado.');
    }

    /**
     * API: Obtiene la proyección de pagos de una orden de servicio.
     * GET /api/service-orders/{serviceOrder}/payment-projection
     */
    public function paymentProjection(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Cargar relaciones necesarias
        $serviceOrder->load(['payments' => function ($q) {
            $q->orderBy('payment_date');
        }, 'client.contacts']);

        $projection = $serviceOrder->getPaymentProjection();

        // Agregar información del cliente para recordatorios
        $primaryContact = $serviceOrder->client->contacts->firstWhere('is_primary', true)
            ?? $serviceOrder->client->contacts->first();

        return response()->json([
            'service_order' => [
                'id' => $serviceOrder->id,
                'status' => $serviceOrder->status,
                'total_amount' => (float) $serviceOrder->total_amount,
                'payment_method' => $serviceOrder->payment_method,
                'down_payment' => (float) ($serviceOrder->down_payment ?? 0),
                'price_per_module' => (float) ($serviceOrder->price_per_module ?? 0),
                'created_at' => $serviceOrder->created_at->format('Y-m-d'),
            ],
            'projection' => $projection,
            'reminder_info' => [
                'has_email' => $primaryContact && !empty($primaryContact->email),
                'has_phone' => $primaryContact && !empty($primaryContact->phone),
                'email' => $primaryContact->email ?? null,
                'phone' => $primaryContact->phone ?? null,
                'contact_name' => $primaryContact->name ?? null,
            ],
        ]);
    }

    /**
     * API: Envía recordatorio de pago al cliente (email y/o WhatsApp).
     * POST /api/service-orders/{serviceOrder}/send-reminder
     */
    public function sendPaymentReminder(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'channel' => 'required|in:email,whatsapp,both',
            'installment' => 'nullable|integer|min:1',
            'message' => 'nullable|string|max:500',
        ]);

        $channel = $request->input('channel');
        $installment = $request->input('installment');
        $customMessage = $request->input('message');

        $serviceOrder->load('client.contacts');
        $primaryContact = $serviceOrder->client->contacts->firstWhere('is_primary', true)
            ?? $serviceOrder->client->contacts->first();

        if (!$primaryContact) {
            return response()->json(['error' => 'El cliente no tiene contactos registrados.'], 422);
        }

        $results = [];
        $baseMessage = $customMessage ?: $this->buildDefaultReminderMessage($serviceOrder, $installment);

        // Enviar por Email
        if (in_array($channel, ['email', 'both']) && !empty($primaryContact->email)) {
            try {
                // Usar el sistema de notificaciones de Laravel (mail)
                Mail::raw($baseMessage, function ($message) use ($primaryContact, $serviceOrder) {
                    $message->to($primaryContact->email, $primaryContact->name)
                        ->subject("Recordatorio de Pago - Orden #{$serviceOrder->id} - Sun's Power MX");
                });
                $results['email'] = ['sent' => true, 'to' => $primaryContact->email];
            } catch (\Exception $e) {
                $results['email'] = ['sent' => false, 'error' => $e->getMessage()];
            }
        } elseif (in_array($channel, ['email', 'both'])) {
            $results['email'] = ['sent' => false, 'error' => 'El contacto no tiene email registrado.'];
        }

        // Enviar por WhatsApp
        if (in_array($channel, ['whatsapp', 'both']) && !empty($primaryContact->phone)) {
            try {
                $phone = preg_replace('/[^0-9]/', '', $primaryContact->phone);
                // Asegurar formato internacional (MX: +52)
                if (strlen($phone) === 10) {
                    $phone = '52' . $phone;
                }
                $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($baseMessage);

                // Si hay integración con API de WhatsApp Business, se usaría aquí.
                // Por ahora devolvemos la URL para que el frontend la abra.
                $results['whatsapp'] = [
                    'sent' => true,
                    'url' => $whatsappUrl,
                    'phone' => $primaryContact->phone,
                ];
            } catch (\Exception $e) {
                $results['whatsapp'] = ['sent' => false, 'error' => $e->getMessage()];
            }
        } elseif (in_array($channel, ['whatsapp', 'both'])) {
            $results['whatsapp'] = ['sent' => false, 'error' => 'El contacto no tiene teléfono registrado.'];
        }

        $allSent = collect($results)->every(fn($r) => $r['sent'] ?? false);

        return response()->json([
            'success' => $allSent,
            'results' => $results,
            'message' => $allSent ? 'Recordatorio enviado correctamente.' : 'Algunos canales no pudieron enviarse.',
        ]);
    }

    /**
     * Construye el mensaje predeterminado de recordatorio de pago.
     * Solo incluye datos de la mensualidad específica (sin número de servicio).
     */
    private function buildDefaultReminderMessage(ServiceOrder $serviceOrder, ?int $installment = null): string
    {
        $clientName = $serviceOrder->client->name;
        $projection = $serviceOrder->getPaymentProjection();
        $installments = $projection['installments'] ?? [];

        $msg = "Estimado(a) {$clientName},\n\n";
        $msg .= "Le recordamos que tiene un pago pendiente con Sun's Power MX.\n\n";

        if ($installment && isset($installments[$installment - 1])) {
            $inst = $installments[$installment - 1];
            $msg .= "Mensualidad: {$inst['label']}\n";
            $msg .= "Monto: \$" . number_format($inst['amount'], 2) . " MXN\n";
            $msg .= "Fecha esperada: " . \Carbon\Carbon::parse($inst['projected_date'])->format('d/m/Y') . "\n\n";
        } else {
            // Buscar la primera cuota pendiente/atrasada
            $nextPending = collect($installments)->first(fn($i) => in_array($i['status'], ['pending', 'late', 'defaulted', 'upcoming']));
            if ($nextPending) {
                $msg .= "Mensualidad: {$nextPending['label']}\n";
                $msg .= "Monto: \$" . number_format($nextPending['amount'], 2) . " MXN\n";
                $msg .= "Fecha esperada: " . \Carbon\Carbon::parse($nextPending['projected_date'])->format('d/m/Y') . "\n\n";
            }
        }

        $msg .= "Por favor, realice su pago a la brevedad para mantener su servicio al corriente.\n\n";
        $msg .= "Si ya realizó su pago, haga caso omiso a este mensaje.\n\n";
        $msg .= "Atentamente,\nSun's Power MX";

        return $msg;
    }

    /**
     * API: Actualiza el método de pago de una orden de servicio.
     * PATCH /api/service-orders/{serviceOrder}/payment-method
     */
    public function updatePaymentMethod(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Validar que no existan pagos de mensualidades (solo se permite anticipo)
        $hasInstallmentPayments = $serviceOrder->payments()
            ->where(function ($q) {
                $q->where('notes', '!=', 'Anticipo')
                  ->orWhereNull('notes');
            })
            ->exists();
        if ($hasInstallmentPayments) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede modificar el plan de pago porque ya existen mensualidades pagadas.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:Contado,3 MSI,6 MSI,9 MSI,12 MSI,Personalizado',
            'down_payment' => 'nullable|numeric|min:0',
        ]);

        $serviceOrder->update($validated);

        // Regenerar cuotas proyectadas según el nuevo plan
        $serviceOrder->generateInstallments();

        return response()->json([
            'success' => true,
            'payment_method' => $serviceOrder->payment_method,
            'message' => 'Plan de pago actualizado correctamente.',
        ]);
    }

    /**
     * API: Actualiza el precio de mantenimiento por módulo.
     * PATCH /api/service-orders/{serviceOrder}/maintenance-price
     */
    public function updateMaintenancePrice(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'price_per_module' => 'nullable|numeric|min:0',
        ]);

        $serviceOrder->update($validated);

        return response()->json([
            'success' => true,
            'price_per_module' => (float) $serviceOrder->price_per_module,
            'message' => 'Precio de mantenimiento actualizado correctamente.',
        ]);
    }

    // ========================================================================
    // NUEVOS ENDPOINTS PARA GESTIÓN DE CUOTAS (PAYMENT INSTALLMENTS)
    // ========================================================================

    /**
     * API: Obtiene las cuotas proyectadas de una orden (desde la BD).
     * GET /api/service-orders/{serviceOrder}/installments
     */
    public function getInstallments(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $projection = $serviceOrder->getPaymentProjection();

        return response()->json($projection);
    }

    /**
     * API: Actualiza una cuota individual (fecha, monto).
     * PATCH /api/installments/{installment}
     */
    public function updateInstallment(Request $request, PaymentInstallment $installment)
    {
        $serviceOrder = $installment->serviceOrder;
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // No permitir editar cuotas ya pagadas
        if ($installment->payment_id || $installment->status === 'paid' || $installment->status === 'on_time') {
            return response()->json([
                'success' => false,
                'error' => 'No se puede modificar una cuota que ya ha sido pagada.',
            ], 422);
        }

        $validated = $request->validate([
            'projected_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'label' => 'nullable|string|max:255',
        ]);

        $updateData = array_filter($validated, fn($v) => $v !== null);
        $installment->update($updateData);

        // Recalcular estatus después del cambio
        $installment->recalculateStatus();

        return response()->json([
            'success' => true,
            'installment' => $installment->fresh(),
            'message' => 'Cuota actualizada correctamente.',
        ]);
    }

    /**
     * API: Marca una cuota como pagada y crea el registro de pago real.
     * POST /api/installments/{installment}/pay
     */
    public function payInstallment(Request $request, PaymentInstallment $installment)
    {
        $serviceOrder = $installment->serviceOrder;
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // No permitir pagar una cuota ya pagada
        if ($installment->payment_id || in_array($installment->status, ['paid', 'on_time'])) {
            return response()->json([
                'success' => false,
                'error' => 'Esta cuota ya ha sido pagada.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'required|in:Efectivo,Transferencia,Tarjeta,Cheque,Depósito,Otro',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        // Crear el pago real
        $payment = Payment::create([
            'branch_id' => $branchId,
            'client_id' => $serviceOrder->client_id,
            'service_order_id' => $serviceOrder->id,
            'installment_number' => $installment->installment_number,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Adjuntar comprobante si se envió
        if ($request->hasFile('proof')) {
            $payment->addMediaFromRequest('proof')->toMediaCollection('receipts');
        }

        // Marcar la cuota como pagada
        $installment->markAsPaid($payment);

        return response()->json([
            'success' => true,
            'payment' => $payment,
            'installment' => $installment->fresh(),
            'message' => 'Pago registrado y cuota actualizada.',
        ]);
    }

    /**
     * API: Liquida todas las cuotas pendientes de una orden en un solo pago.
     * POST /api/service-orders/{serviceOrder}/liquidate
     */
    public function liquidateOrder(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'required|in:Efectivo,Transferencia,Tarjeta,Cheque,Depósito,Otro',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        // Obtener cuotas pendientes
        $pendingInstallments = $serviceOrder->paymentInstallments()
            ->whereNull('payment_id')
            ->whereNotIn('status', ['paid', 'on_time'])
            ->get();

        if ($pendingInstallments->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No hay cuotas pendientes por liquidar.',
            ], 422);
        }

        $totalPending = $pendingInstallments->sum('amount');

        if ($validated['amount'] < $totalPending - 1) {
            return response()->json([
                'success' => false,
                'error' => "El monto mínimo para liquidar es de $" . number_format($totalPending, 2),
            ], 422);
        }

        // Crear un solo pago por el total
        $payment = Payment::create([
            'branch_id' => $branchId,
            'client_id' => $serviceOrder->client_id,
            'service_order_id' => $serviceOrder->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? 'Liquidación total',
        ]);

        if ($request->hasFile('proof')) {
            $payment->addMediaFromRequest('proof')->toMediaCollection('receipts');
        }

        // Distribuir el pago entre todas las cuotas pendientes
        $remainingAmount = $validated['amount'];
        foreach ($pendingInstallments as $inst) {
            if ($remainingAmount <= 0) break;
            $allocatedAmount = min($inst->amount, $remainingAmount);
            $inst->update([
                'status' => 'paid',
                'paid_amount' => $allocatedAmount,
                'paid_date' => $validated['payment_date'],
                'payment_id' => $payment->id,
            ]);
            $remainingAmount -= $allocatedAmount;
        }

        // Si el pago cubre más de lo pendiente, asignar el excedente a la última cuota
        if ($remainingAmount > 0 && $pendingInstallments->isNotEmpty()) {
            $lastInst = $pendingInstallments->last();
            $lastInst->increment('paid_amount', $remainingAmount);
        }

        return response()->json([
            'success' => true,
            'payment' => $payment,
            'message' => 'Orden liquidada correctamente. Todas las cuotas han sido marcadas como pagadas.',
        ]);
    }
}