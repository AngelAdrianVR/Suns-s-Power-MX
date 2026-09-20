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
use App\Mail\PaymentReminderMail;
use App\Models\ServiceOrderEvidence;
use App\Models\ServiceDocumentationAttachment;
use App\Models\SystemType;
use App\Models\User;
use App\Models\TaskTemplate; 
use App\Models\Ticket;
use App\Models\ServiceDocumentationStep;
use App\Models\TechnicalVisit;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

        // Pasos de documentación de servicio configurados para esta sucursal
        $documentationSteps = ServiceDocumentationStep::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

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
            'can_view_financials' => $canViewFinancials,
            'documentation_steps' => $documentationSteps,
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
                Mail::to($primaryContact->email, $primaryContact->name)
                    ->send(new PaymentReminderMail($serviceOrder, $baseMessage, $installment));
                $results['email'] = ['sent' => true, 'to' => $primaryContact->email];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error enviando recordatorio email', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'to' => $primaryContact->email,
                    'order_id' => $serviceOrder->id,
                ]);
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
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $serviceOrder->update($validated);

        // Sincronizar el pago de anticipo REAL (fuente de verdad para saldos y cuotas).
        // El campo down_payment de la orden es solo metadata; el registro en payments
        // es lo que se muestra en "Pagos Realizados" y descuenta el saldo.
        $downPayment = (float) ($validated['down_payment'] ?? 0);
        $existingAnticipo = $serviceOrder->payments()
            ->where('notes', 'Anticipo')
            ->latest('id')
            ->first();

        if ($downPayment > 0) {
            // Si ya existe un anticipo con el mismo monto, conservarlo (y su comprobante)
            if ($existingAnticipo && (float) $existingAnticipo->amount === $downPayment) {
                $anticipoPayment = $existingAnticipo;
                // Reemplazar el comprobante solo si se subió uno nuevo
                if ($request->hasFile('proof')) {
                    $anticipoPayment->clearMediaCollection('receipts');
                    $anticipoPayment->addMediaFromRequest('proof')->toMediaCollection('receipts');
                }
            } else {
                // Monto distinto o sin anticipo previo: eliminar el anterior y crear el nuevo
                if ($existingAnticipo) {
                    $existingAnticipo->delete();
                }
                $anticipoPayment = Payment::create([
                    'branch_id' => $branchId,
                    'client_id' => $serviceOrder->client_id,
                    'service_order_id' => $serviceOrder->id,
                    'amount' => $downPayment,
                    'payment_date' => now(),
                    'method' => 'Transferencia',
                    'notes' => 'Anticipo',
                ]);

                if ($request->hasFile('proof')) {
                    $anticipoPayment->addMediaFromRequest('proof')->toMediaCollection('receipts');
                }
            }
        } else {
            // Sin anticipo: eliminar el registro previo si existía
            if ($existingAnticipo) {
                $existingAnticipo->delete();
            }
        }

        // Regenerar cuotas proyectadas según el nuevo plan (usa el anticipo real)
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

    /**
     * API: Actualiza (o limpia) las coordenadas de la instalación.
     * PATCH /api/service-orders/{serviceOrder}/coordinates
     */
    public function updateCoordinates(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'installation_lat' => 'nullable|numeric|between:-90,90',
            'installation_lng' => 'nullable|numeric|between:-180,180',
        ]);

        // Solo se guardan coordenadas si vienen ambas; una sola no es útil para el mapa.
        $hasBoth = $request->filled('installation_lat') && $request->filled('installation_lng');

        $serviceOrder->update([
            'installation_lat' => $hasBoth ? $request->input('installation_lat') : null,
            'installation_lng' => $hasBoth ? $request->input('installation_lng') : null,
        ]);

        return response()->json([
            'success' => true,
            'installation_lat' => $serviceOrder->installation_lat,
            'installation_lng' => $serviceOrder->installation_lng,
            'message' => 'Ubicación actualizada correctamente.',
        ]);
    }

    // ========================================================================
    // NÚMEROS DE SERIE DE PANELES + DIAGRAMA UNIFILAR
    // ========================================================================

    /**
     * Arreglo de paneles (número + serie) alineado con number_of_units.
     */
    private function unifilarPanels(ServiceOrder $serviceOrder): array
    {
        $units = max(0, (int) ($serviceOrder->number_of_units ?? 0));
        $serials = $serviceOrder->panel_serials ?? [];

        $panels = [];
        for ($i = 0; $i < $units; $i++) {
            $panels[] = [
                'number' => $i + 1,
                'serial' => trim((string) ($serials[$i] ?? '')),
            ];
        }

        return $panels;
    }

    /**
     * Microinversores del diagrama (uno por cada rama de 4 paneles),
     * con valores guardados o los valores por defecto.
     */
    private function unifilarMicroinverters(ServiceOrder $serviceOrder, int $groupCount): array
    {
        $stored = $serviceOrder->microinverters ?? [];
        $defaultModel = 'MICROINVERSOR SOLAX POWER X1-MICRO 2500 G2';

        $micros = [];
        for ($i = 0; $i < $groupCount; $i++) {
            $micros[] = [
                'model' => trim((string) ($stored[$i]['model'] ?? '')) ?: $defaultModel,
                'serial' => trim((string) ($stored[$i]['serial'] ?? '')) ?: 'MI-'.($i + 1),
            ];
        }

        return $micros;
    }

    /**
     * API: Actualiza los números de serie de los paneles instalados.
     * PATCH /api/service-orders/{serviceOrder}/panel-serials
     */
    public function updatePanelSerials(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'panel_serials' => 'nullable|array',
            'panel_serials.*' => 'nullable|string|max:255',
        ]);

        // Solo se conservan tantas series como unidades instaladas haya
        $units = max(0, (int) ($serviceOrder->number_of_units ?? 0));
        $serials = [];
        for ($i = 0; $i < $units; $i++) {
            $serials[] = trim((string) ($validated['panel_serials'][$i] ?? ''));
        }

        $serviceOrder->update(['panel_serials' => $serials]);

        return response()->json([
            'success' => true,
            'panel_serials' => $serviceOrder->panel_serials,
            'message' => 'Números de serie guardados correctamente.',
        ]);
    }

    /**
     * API: Actualiza los datos editables del diagrama unifilar
     * (números de serie de paneles y textos de los microinversores).
     * PATCH /api/service-orders/{serviceOrder}/diagram-data
     */
    public function updateDiagramData(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'panel_serials' => 'nullable|array',
            'panel_serials.*' => 'nullable|string|max:255',
            'microinverters' => 'nullable|array',
            'microinverters.*.model' => 'nullable|string|max:255',
            'microinverters.*.serial' => 'nullable|string|max:255',
        ]);

        // Series de paneles: solo tantas como unidades instaladas haya
        $units = max(0, (int) ($serviceOrder->number_of_units ?? 0));
        $serials = [];
        for ($i = 0; $i < $units; $i++) {
            $serials[] = trim((string) ($validated['panel_serials'][$i] ?? ''));
        }

        // Microinversores: una entrada por rama (cada 4 paneles)
        $micros = [];
        foreach ($validated['microinverters'] ?? [] as $micro) {
            $micros[] = [
                'model' => trim((string) ($micro['model'] ?? '')),
                'serial' => trim((string) ($micro['serial'] ?? '')),
            ];
        }

        $serviceOrder->update([
            'panel_serials' => $serials,
            'microinverters' => $micros,
        ]);

        return response()->json([
            'success' => true,
            'panel_serials' => $serviceOrder->panel_serials,
            'microinverters' => $serviceOrder->microinverters,
            'message' => 'Datos del diagrama guardados correctamente.',
        ]);
    }

    /**
     * Vista del diagrama unifilar (se abre en pestaña nueva, sin AppLayout).
     * GET /ordenes-servicio/{serviceOrder}/diagrama-unifilar
     */
    public function unifilarDiagram(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            abort(403);
        }

        $serviceOrder->load('client');

        $panels = $this->unifilarPanels($serviceOrder);
        $microinverters = $this->unifilarMicroinverters(
            $serviceOrder,
            (int) ceil(count($panels) / 4)
        );

        return Inertia::render('ServiceOrders/DiagramUnifilar', [
            'order' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number,
                'status' => $serviceOrder->status,
                'system_type' => $serviceOrder->system_type,
                'number_of_units' => max(0, (int) ($serviceOrder->number_of_units ?? 0)),
                'client' => $serviceOrder->client?->name,
                'installation_address' => $serviceOrder->full_installation_address,
            ],
            'panels' => $panels,
            'microinverters' => $microinverters,
            'linked' => $serviceOrder->getMedia('diagram_unifilar')->isNotEmpty(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Vincula el diagrama unifilar a la orden como archivo adjunto
     * (aparece en Evidencias y Documentos, igual que un archivo subido desde esa pestaña).
     * POST /ordenes-servicio/{serviceOrder}/diagrama-unifilar/vincular
     */
    public function linkUnifilarDiagram(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $serviceOrder->load('client');

        $panels = $this->unifilarPanels($serviceOrder);
        $groups = array_chunk($panels, 4);
        $microinverters = $this->unifilarMicroinverters($serviceOrder, count($groups));

        $pdf = Pdf::loadView('pdf.diagrama-unifilar', [
            'order' => $serviceOrder,
            'panels' => $panels,
            'groups' => $groups,
            'microinverters' => $microinverters,
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape');

        $fileName = 'diagrama-unifilar-orden-'.$serviceOrder->id.'.pdf';

        // Se reemplaza el diagrama vinculado anterior (si existe) para no duplicar
        $serviceOrder->clearMediaCollection('diagram_unifilar');

        $tmpPath = tempnam(sys_get_temp_dir(), 'unifilar');
        file_put_contents($tmpPath, $pdf->output());

        try {
            $serviceOrder->addMedia($tmpPath)
                ->usingName('Diagrama Unifilar')
                ->usingFileName($fileName)
                ->withCustomProperties(['category' => 'diagrama_unifilar'])
                ->toMediaCollection('diagram_unifilar');
        } finally {
            @unlink($tmpPath);
        }

        return response()->json([
            'success' => true,
            'linked' => true,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'message' => 'Diagrama unifilar vinculado a la orden de servicio.',
        ]);
    }

    // ========================================================================
    // SOLICITUD ARCO CFE (carta editable que se vincula a la orden)
    // ========================================================================

    /**
     * Enlace "VER" del domicilio en Google Maps (coordenadas o dirección).
     */
    private function mapsUrl(ServiceOrder $serviceOrder): ?string
    {
        if ($serviceOrder->installation_lat && $serviceOrder->installation_lng) {
            return "https://www.google.com/maps/dir/?api=1&destination={$serviceOrder->installation_lat},{$serviceOrder->installation_lng}";
        }

        $addressQuery = [
            $serviceOrder->installation_street,
            $serviceOrder->installation_exterior_number,
            $serviceOrder->installation_neighborhood,
            $serviceOrder->installation_municipality,
            $serviceOrder->installation_state,
            $serviceOrder->installation_country ?? 'México',
        ];

        $finalQuery = collect($addressQuery)->filter()->implode(', ') ?: $serviceOrder->installation_address;

        if (!$finalQuery) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($finalQuery);
    }

    /**
     * Vista de la Solicitud Arco CFE (pestaña nueva, sin AppLayout).
     * GET /ordenes-servicio/{serviceOrder}/solicitud-arco-cfe
     */
    public function solicitudArcoCfe(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            abort(403);
        }

        $serviceOrder->load('client.contacts');

        $contact = $serviceOrder->client?->contacts->first();

        $fields = [
            'fecha' => now()->format('d/m/Y'),
            'servicio' => (string) ($serviceOrder->service_number ?? ''),
            'cliente' => (string) ($serviceOrder->client?->name ?? ''),
            'domicilio' => (string) $serviceOrder->full_installation_address,
            'maps_url' => (string) ($this->mapsUrl($serviceOrder) ?? ''),
            'rfc' => (string) ($serviceOrder->client?->tax_id ?? ''),
            'regimen' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            'uso_cfdi' => 'Sin efectos fiscales',
            'telefono' => (string) ($contact?->phone ?? ''),
            'correo' => (string) ($contact?->email ?? ''),
            'calle' => (string) ($serviceOrder->installation_street ?? ''),
            'entre_calles' => '',
            'titular' => (string) ($serviceOrder->client?->name ?? ''),
            'firma_nombre' => '',
        ];

        return Inertia::render('ServiceOrders/SolicitudArcoCfe', [
            'order' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number,
            ],
            'fields' => $fields,
            'linked' => $serviceOrder->getMedia('solicitud_arco_cfe')->isNotEmpty(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Genera el PDF de la Solicitud Arco CFE con los datos editados y lo vincula a la orden.
     * POST /ordenes-servicio/{serviceOrder}/solicitud-arco-cfe/vincular
     */
    public function linkSolicitudArcoCfe(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:500',
        ]);

        $fields = array_merge([
            'fecha' => now()->format('d/m/Y'),
            'servicio' => '',
            'cliente' => '',
            'domicilio' => '',
            'maps_url' => '',
            'rfc' => '',
            'regimen' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            'uso_cfdi' => 'Sin efectos fiscales',
            'telefono' => '',
            'correo' => '',
            'calle' => '',
            'entre_calles' => '',
            'titular' => '',
            'firma_nombre' => '',
        ], $validated['fields']);

        $pdf = Pdf::loadView('pdf.solicitud-arco-cfe', [
            'fields' => $fields,
            'order' => $serviceOrder,
            'generatedAt' => now(),
        ])->setPaper('letter');

        $fileName = 'solicitud-arco-cfe-orden-'.$serviceOrder->id.'.pdf';

        // Se reemplaza la solicitud vinculada anterior (si existe) para no duplicar
        $serviceOrder->clearMediaCollection('solicitud_arco_cfe');

        $tmpPath = tempnam(sys_get_temp_dir(), 'arco');
        file_put_contents($tmpPath, $pdf->output());

        try {
            $serviceOrder->addMedia($tmpPath)
                ->usingName('Solicitud Arco CFE')
                ->usingFileName($fileName)
                ->withCustomProperties(['category' => 'solicitud_arco_cfe'])
                ->toMediaCollection('solicitud_arco_cfe');
        } finally {
            @unlink($tmpPath);
        }

        return response()->json([
            'success' => true,
            'linked' => true,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'message' => 'Solicitud Arco CFE vinculada a la orden de servicio.',
        ]);
    }

    // ========================================================================
    // CARTA PODER (otorgante = cliente; apoderado y testigos = usuarios)
    // ========================================================================

    /**
     * Nombre del mes en español (para la fecha escrita de la carta).
     */
    private function mesEnLetra(int $month): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $meses[$month] ?? '';
    }

    /**
     * Convierte el archivo INE de un usuario en una página completa del PDF.
     */
    private function inePagePayload(Media $media, string $personName): array
    {
        $gdAvailable = extension_loaded('gd');
        $path = $media->getPath();
        $payload = null;
        $needsGd = false;

        if (is_file($path) && is_readable($path) && filesize($path) > 0) {
            $binary = @file_get_contents($path);
            $dims = $binary !== false ? @getimagesizefromstring($binary) : false;

            if ($dims && !empty($dims[0]) && !empty($dims[1])) {
                $mime = strtolower((string) ($dims['mime'] ?? ''));

                // Dompdf incrusta JPEG sin GD. PNG/WebP/GIF/BMP sí requieren GD.
                if ($mime === 'image/jpeg' || $gdAvailable) {
                    $payload = $this->imagePagePayloadFromBinary($binary, $mime, $personName, $media->file_name);
                } else {
                    $needsGd = true;
                }
            }
        }

        return $payload ?? [
            'person' => $personName,
            'src' => null,
            'w' => 192.0,
            'h' => null,
            'is_image' => false,
            'file_name' => $media->file_name,
            'needs_gd' => $needsGd,
        ];
    }

    /**
     * Payload de una imagen incrustada en el HTML como Data URI.
     *
     * Los bytes de la imagen van dentro del propio HTML (y no la ruta del
     * archivo) porque en producción Dompdf puede no poder resolver rutas
     * temporales o de disco (permisos, chroot, open_basedir, etc.) y en su
     * lugar dibuja un recuadro con una "X". Con Data URI Dompdf solo necesita
     * su carpeta temporal, que se configura dentro de storage.
     */
    private function imagePagePayloadFromBinary(string $binary, string $mime, string $personName, string $file_name): ?array
    {
        $dims = @getimagesizefromstring($binary);

        if (!$dims || empty($dims[0]) || empty($dims[1])) {
            return null;
        }

        $pageW = 192.0;
        $pageH = 245.0;
        $width = $pageW;
        $height = null;

        $ratio = $dims[1] / $dims[0];

        if ($ratio > $pageH / $pageW) {
            $height = $pageH;
            $width = $pageH / $ratio;
        } else {
            $width = $pageW;
            $height = $width * $ratio;
        }

        return [
            'person' => $personName,
            'src' => 'data:'.$mime.';base64,'.base64_encode($binary),
            'w' => round($width, 1),
            'h' => round($height, 1),
            'is_image' => true,
            'file_name' => $file_name,
            'needs_gd' => false,
        ];
    }

    /**
     * Imagen a partir del Data URI JPEG que convierte el navegador (no requiere GD).
     */
    private function imagePagePayloadFromDataUrl(string $dataUrl, string $personName, string $file_name): ?array
    {
        if (!preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', trim($dataUrl), $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        return $this->imagePagePayloadFromBinary($binary, strtolower($matches[1]), $personName, $file_name);
    }

    /**
     * Si Dompdf no pudo incrustar alguna imagen (dibuja una "X" en su lugar),
     * el motivo queda registrado en el log para poder diagnosticarlo.
     */
    private function logDompdfImageWarnings(int $serviceOrderId): void
    {
        foreach ((array) ($GLOBALS['_dompdf_warnings'] ?? []) as $warning) {
            $warning = (string) $warning;

            if (stripos($warning, 'image') === false) {
                continue;
            }

            Log::warning('Dompdf no pudo incrustar una imagen en la Carta Poder', [
                'service_order_id' => $serviceOrderId,
                'warning' => $warning,
            ]);
        }
    }

    /**
     * Reapunta los adjuntos del expediente que apuntaban a la versión anterior
     * de un documento regenerado (carta poder, etc.). Sin esto, el expediente
     * sigue apuntando al archivo viejo (ya eliminado) y no puede imprimirlo.
     */
    private function relinkDocumentationAttachments(ServiceOrder $serviceOrder, array $oldMediaIds, Media $newMedia): void
    {
        if (empty($oldMediaIds)) {
            return;
        }

        ServiceDocumentationAttachment::query()
            ->where('service_order_id', $serviceOrder->id)
            ->whereIn('media_id', $oldMediaIds)
            ->update([
                'media_id' => $newMedia->id,
                'file_name' => $newMedia->file_name,
                'mime_type' => $newMedia->mime_type,
                'file_path' => $newMedia->getPath(),
                'url' => $newMedia->getUrl(),
            ]);
    }

    /**
     * Vista de la Carta Poder (pestaña nueva, sin AppLayout).
     * GET /ordenes-servicio/{serviceOrder}/carta-poder
     */
    public function cartaPoder(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            abort(403);
        }

        $serviceOrder->load('client');

        $today = now();

        $fields = [
            'ciudad' => (string) ($serviceOrder->installation_municipality ?? ''),
            'dia' => $today->format('d'),
            'mes' => $this->mesEnLetra((int) $today->format('n')),
            'anio' => (string) $today->year,
            'vigencia' => '6 meses',
            'otorgante_nombre' => (string) ($serviceOrder->client?->name ?? ''),
            'otorgante_domicilio' => (string) ($serviceOrder->client?->fullAddress ?? ''),
            'otorgante_ine' => '',
            'apoderado_nombre' => '',
            'apoderado_ine' => '',
            'testigo1_nombre' => '',
            'testigo1_ine' => '',
            'testigo2_nombre' => '',
            'testigo2_ine' => '',
        ];

        $users = User::where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'ine_number'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'ine_number' => $user->ine_number,
                    'media' => $user->getMedia('documents')->map(fn ($m) => [
                        'id' => $m->id,
                        'file_name' => $m->file_name,
                        'mime_type' => $m->mime_type,
                        'url' => $m->getUrl(),
                    ])->values(),
                ];
            })
            ->values();

        return Inertia::render('ServiceOrders/CartaPoder', [
            'order' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number,
            ],
            'fields' => $fields,
            'users' => $users,
            'linked' => $serviceOrder->getMedia('carta_poder')->isNotEmpty(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Genera el PDF de la Carta Poder (carta + una hoja por INE) y lo vincula a la orden.
     * POST /ordenes-servicio/{serviceOrder}/carta-poder/vincular
     */
    public function linkCartaPoder(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:500',
            'apoderado_id' => 'nullable|integer',
            'testigo1_id' => 'nullable|integer',
            'testigo2_id' => 'nullable|integer',
            'ine_media' => 'nullable|array',
            'ine_media.*' => 'nullable|integer',
            'ine_images' => 'nullable|array',
            'ine_images.*' => 'nullable|string',
        ]);

        $fields = array_merge([
            'ciudad' => '',
            'dia' => now()->format('d'),
            'mes' => '',
            'anio' => (string) now()->year,
            'vigencia' => '6 meses',
            'otorgante_nombre' => '',
            'otorgante_domicilio' => '',
            'otorgante_ine' => '',
            'apoderado_nombre' => '',
            'apoderado_ine' => '',
            'testigo1_nombre' => '',
            'testigo1_ine' => '',
            'testigo2_nombre' => '',
            'testigo2_ine' => '',
        ], $validated['fields']);

        $roles = [
            'apoderado' => 'apoderado_id',
            'testigo1' => 'testigo1_id',
            'testigo2' => 'testigo2_id',
        ];

        $userIds = array_filter(array_map(fn ($key) => (int) ($validated[$key] ?? 0), $roles));
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $ineImages = $validated['ine_images'] ?? [];

        $inePages = [];
        foreach ($roles as $role => $key) {
            $userId = (int) ($validated[$key] ?? 0);
            $personName = (string) ($users[$userId]->name ?? $fields[$role.'_nombre'] ?? $role);

            // 1) Imagen JPEG convertida en el navegador: se incrusta directo en el
            //    PDF como Data URI (no depende de archivos temporales ni de GD).
            if ($userId && !empty($ineImages[$role])) {
                $payload = $this->imagePagePayloadFromDataUrl((string) $ineImages[$role], $personName, 'INE.jpg');

                if ($payload !== null) {
                    $inePages[$role] = $payload;
                    continue;
                }
            }

            // 2) Respaldo: archivo original vinculado al usuario
            $mediaId = (int) ($validated['ine_media'][$role] ?? 0);
            $media = null;
            if ($userId && $mediaId) {
                $media = Media::query()
                    ->where('id', $mediaId)
                    ->where('model_type', User::class)
                    ->where('model_id', $userId)
                    ->first();
            }

            $inePages[$role] = $media ? $this->inePagePayload($media, $personName) : null;
        }

        // Las imágenes van dentro del HTML como Data URI y Dompdf las copia a su
        // carpeta temporal para poder incrustarlas. Se usa una carpeta dentro de
        // storage (escribible siempre) en lugar de la del sistema, que en
        // producción puede estar restringida y provocar los recuadros con "X".
        $dompdfTempDir = storage_path('app/dompdf-temp');
        if (!is_dir($dompdfTempDir)) {
            @mkdir($dompdfTempDir, 0775, true);
        }

        unset($GLOBALS['_dompdf_warnings']);

        $pdf = Pdf::setOption('temp_dir', $dompdfTempDir)->loadView('pdf.carta-poder', [
            'fields' => $fields,
            'ine_pages' => $inePages,
            'order' => $serviceOrder,
            'generatedAt' => now(),
        ])->setPaper('letter');

        $fileName = 'carta-poder-orden-'.$serviceOrder->id.'.pdf';

        // Se reemplaza la carta vinculada anterior (si existe) para no duplicar
        $oldCartaMediaIds = $serviceOrder->getMedia('carta_poder')->pluck('id')->all();
        $serviceOrder->clearMediaCollection('carta_poder');

        $tmpPath = $dompdfTempDir.'/carta-poder-orden-'.$serviceOrder->id.'-'.uniqid().'.pdf';
        file_put_contents($tmpPath, $pdf->output());

        // Si Dompdf no pudo incrustar alguna imagen, el motivo queda en el log.
        $this->logDompdfImageWarnings($serviceOrder->id);

        try {
            $newMedia = $serviceOrder->addMedia($tmpPath)
                ->usingName('Carta Poder')
                ->usingFileName($fileName)
                ->withCustomProperties(['category' => 'carta_poder'])
                ->toMediaCollection('carta_poder');
        } finally {
            @unlink($tmpPath);
        }

        // El expediente apunta a la versión anterior (ya eliminada): se reapunta
        // a la nueva para que muestre e imprima siempre la última carta.
        $this->relinkDocumentationAttachments($serviceOrder, $oldCartaMediaIds, $newMedia);

        return response()->json([
            'success' => true,
            'linked' => true,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'message' => 'Carta Poder vinculada a la orden de servicio.',
        ]);
    }

    // ========================================================================
    // CAMBIO DE NOMBRE (solicitud de contrato por cambio de titular ante CFE)
    // ========================================================================

    /**
     * Vista de la solicitud Cambio de Nombre (pestaña nueva, sin AppLayout).
     * GET /ordenes-servicio/{serviceOrder}/cambio-de-nombre
     */
    public function cambioDeNombre(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            abort(403);
        }

        $serviceOrder->load('client.contacts');

        $contact = $serviceOrder->client?->contacts->first();

        $fields = [
            'fecha' => now()->format('d/m/Y'),
            'solicitante_nombre' => (string) ($serviceOrder->client?->name ?? ''),
            'solicitante_calidad' => 'Propietario',
            'id_tipo' => 'ife',
            'id_otro' => '',
            'id_numero' => '',
            'rep_tipo' => 'na',
            'rep_acta_no' => '',
            'rep_no' => '',
            'rep_otro' => '',
            'servicio' => (string) ($serviceOrder->service_number ?? ''),
            'titular_actual' => (string) ($serviceOrder->client?->name ?? ''),
            'domicilio' => (string) ($serviceOrder->full_installation_address ?? ''),
            'motivo' => '',
            'nuevo_titular' => (string) ($serviceOrder->client?->name ?? ''),
            'doc_escritura' => false,
            'doc_compraventa' => false,
            'doc_gravamen' => false,
            'doc_predial' => false,
            'doc_ine' => true,
            'doc_arrendamiento_certificado' => false,
            'doc_arrendamiento_simple' => false,
            'doc_constancia' => false,
            'tiene_rfc' => (bool) ($serviceOrder->client?->tax_id ?? ''),
            'rfc' => (string) ($serviceOrder->client?->tax_id ?? ''),
            'tiene_telefono' => (bool) ($contact?->phone ?? ''),
            'telefono' => (string) ($contact?->phone ?? ''),
            'tiene_celular' => false,
            'celular' => '',
            'tiene_correo' => (bool) ($contact?->email ?? ''),
            'correo' => (string) ($contact?->email ?? ''),
            'timbrado' => 'No',
            'csf_nombre' => (string) ($serviceOrder->client?->name ?? ''),
            'csf_cp' => (string) ($serviceOrder->installation_zip_code ?? ''),
            'csf_regimen' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            'csf_uso' => 'Sin efectos fiscales',
            'csf_residencia' => '',
            'csf_registro' => '',
            'firma_nombre' => '',
        ];

        $users = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'ine_number', 'rfc'])
            ->values();

        return Inertia::render('ServiceOrders/CambioDeNombre', [
            'order' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number,
            ],
            'fields' => $fields,
            'users' => $users,
            'linked' => $serviceOrder->getMedia('cambio_de_nombre')->isNotEmpty(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Genera el PDF de Cambio de Nombre con los datos editados y lo vincula a la orden.
     * POST /ordenes-servicio/{serviceOrder}/cambio-de-nombre/vincular
     */
    public function linkCambioDeNombre(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:1000',
            'user_id' => 'nullable|integer',
        ]);

        $fields = array_merge([
            'fecha' => now()->format('d/m/Y'),
            'solicitante_nombre' => '',
            'solicitante_calidad' => 'Propietario',
            'id_tipo' => 'ife',
            'id_otro' => '',
            'id_numero' => '',
            'rep_tipo' => 'na',
            'rep_acta_no' => '',
            'rep_no' => '',
            'rep_otro' => '',
            'servicio' => '',
            'titular_actual' => '',
            'domicilio' => '',
            'motivo' => '',
            'nuevo_titular' => '',
            'doc_escritura' => false,
            'doc_compraventa' => false,
            'doc_gravamen' => false,
            'doc_predial' => false,
            'doc_ine' => false,
            'doc_arrendamiento_certificado' => false,
            'doc_arrendamiento_simple' => false,
            'doc_constancia' => false,
            'tiene_rfc' => false,
            'rfc' => '',
            'tiene_telefono' => false,
            'telefono' => '',
            'tiene_celular' => false,
            'celular' => '',
            'tiene_correo' => false,
            'correo' => '',
            'timbrado' => 'No',
            'csf_nombre' => '',
            'csf_cp' => '',
            'csf_regimen' => '',
            'csf_uso' => '',
            'csf_residencia' => '',
            'csf_registro' => '',
            'firma_nombre' => '',
        ], $validated['fields']);

        // Las casillas llegan como '1'/'0' desde el front
        $booleanFields = [
            'doc_escritura', 'doc_compraventa', 'doc_gravamen', 'doc_predial', 'doc_ine',
            'doc_arrendamiento_certificado', 'doc_arrendamiento_simple', 'doc_constancia',
            'tiene_rfc', 'tiene_telefono', 'tiene_celular', 'tiene_correo',
        ];
        foreach ($booleanFields as $key) {
            $fields[$key] = filter_var($fields[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $pdf = Pdf::loadView('pdf.cambio-de-nombre', [
            'fields' => $fields,
            'order' => $serviceOrder,
            'generatedAt' => now(),
        ])->setPaper('letter');

        $fileName = 'cambio-de-nombre-orden-'.$serviceOrder->id.'.pdf';

        // Se reemplaza la solicitud vinculada anterior (si existe) para no duplicar
        $serviceOrder->clearMediaCollection('cambio_de_nombre');

        $tmpPath = tempnam(sys_get_temp_dir(), 'cambionombre');
        file_put_contents($tmpPath, $pdf->output());

        try {
            $serviceOrder->addMedia($tmpPath)
                ->usingName('Cambio de Nombre')
                ->usingFileName($fileName)
                ->withCustomProperties(['category' => 'cambio_de_nombre'])
                ->toMediaCollection('cambio_de_nombre');
        } finally {
            @unlink($tmpPath);
        }

        return response()->json([
            'success' => true,
            'linked' => true,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'message' => 'Cambio de Nombre vinculado a la orden de servicio.',
        ]);
    }

    // ========================================================================
    // ANEXO 2 (Solicitud de Interconexión - datos del solicitante y contacto)
    // ========================================================================

    /**
     * Convierte a mayúsculas todos los valores de texto del formulario,
     * respetando las claves de opciones que controlan las casillas.
     */
    private function upperCaseFields(array $fields): array
    {
        $optionKeys = ['modalidad', 'utilizacion', 'tecnologia', 'manifiesto'];

        foreach ($fields as $key => $value) {
            if (is_string($value) && ! in_array($key, $optionKeys, true)) {
                $fields[$key] = mb_strtoupper($value, 'UTF-8');
            }
        }

        return $fields;
    }

    /**
     * Vista del Anexo 2 (pestaña nueva, sin AppLayout).
     * GET /ordenes-servicio/{serviceOrder}/anexo-2
     */
    public function anexo2(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            abort(403);
        }

        $serviceOrder->load('client.contacts');

        $client = $serviceOrder->client;
        $contact = $client?->contacts->first();

        // Generación promedio mensual estimada = generación diaria × 30.
        // Se toma de la visita técnica de la orden; si no hay datos, se estima con la capacidad instalada.
        $visit = TechnicalVisit::where('service_order_id', $serviceOrder->id)->latest('id')->first();

        $dailyGeneration = (float) ($visit?->estimated_daily_generation ?? 0);
        $monthlyGeneration = (float) ($visit?->estimated_monthly_generation ?? 0);

        if ($monthlyGeneration <= 0 && $dailyGeneration > 0) {
            $monthlyGeneration = $dailyGeneration * 30;
        }

        if ($monthlyGeneration <= 0 && $serviceOrder->total_capacity) {
            // Mismo criterio de visitas técnicas: capacidad bruta (kW) × 3.76 kWh al día
            $monthlyGeneration = (float) $serviceOrder->total_capacity * 3.76 * 30;
        }

        $fields = [
            'fecha' => '', // En blanco, editable
            'num_solicitud' => '',

            // I. Datos del Solicitante (cliente de la orden)
            'sol_nombre' => (string) ($client?->name ?? ''),
            'sol_calle' => (string) ($client?->street ?? ''),
            'sol_num_ext' => (string) ($client?->exterior_number ?? ''),
            'sol_num_int' => (string) ($client?->interior_number ?? ''),
            'sol_cp' => (string) ($client?->zip_code ?? ''),
            'sol_colonia' => (string) ($client?->neighborhood ?? ''),
            'sol_municipio' => (string) ($client?->municipality ?? ''),
            'sol_estado' => (string) ($client?->state ?? ''),
            'sol_telefono' => (string) ($contact?->phone ?? ''),
            'sol_correo' => (string) ($contact?->email ?? ''),
            'sol_fax' => '',

            // II. Datos de Contacto (usuario del ERP)
            'con_nombre' => '',
            'con_puesto' => '',
            'con_calle' => '',
            'con_num_ext' => '',
            'con_num_int' => '',
            'con_cp' => '',
            'con_colonia' => '',
            'con_municipio' => '',
            'con_estado' => '',
            'con_telefono' => '',
            'con_correo' => '',
            'con_fax' => '',

            // III. Datos de la Solicitud
            'modalidad' => 'baja',

            // IV. Utilización de la energía
            'utilizacion' => 'centros',

            // V. Datos del Servicio Suministro Actual
            'rpu' => (string) ($serviceOrder->service_number ?? ''),
            'nivel_tension' => (string) ($serviceOrder->voltage ?? ''),

            // VI. Central Eléctrica
            'fecha_operacion' => '',
            'capacidad_bruta' => $serviceOrder->total_capacity !== null ? (string) $serviceOrder->total_capacity : '',
            'capacidad_incrementar' => '',
            'generacion_promedio' => $monthlyGeneration > 0 ? (string) round($monthlyGeneration, 2) : '',

            // VII. Manifestación y especificaciones
            'manifiesto' => 'Si',
            'tecnologia' => 'solar',
            'tecnologia_otro' => '',
            'num_unidades' => $serviceOrder->number_of_units !== null ? (string) $serviceOrder->number_of_units : '',
            'combustible_principal' => '',
            'combustible_secundario' => '',

            // Firma
            'firma_nombre' => (string) ($client?->name ?? ''),
            'firma_cargo' => 'TITULAR',
            'firma_fecha' => '',
        ];

        // Coordenadas UTM (6 filas editables)
        for ($i = 1; $i <= 6; $i++) {
            $fields["utm_x{$i}"] = '';
            $fields["utm_y{$i}"] = '';
        }

        // Fila 1: X = latitud, Y = longitud (si la orden cuenta con coordenadas)
        if ($serviceOrder->installation_lat) {
            $fields['utm_x1'] = (string) $serviceOrder->installation_lat;
        }
        if ($serviceOrder->installation_lng) {
            $fields['utm_y1'] = (string) $serviceOrder->installation_lng;
        }

        // Todo el texto del documento en mayúsculas
        $fields = $this->upperCaseFields($fields);

        $users = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('name')
            ->get([
                'id', 'name', 'phone', 'email', 'rfc', 'ine_number',
                'street', 'exterior_number', 'interior_number', 'neighborhood',
                'zip_code', 'municipality', 'state',
            ])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'rfc' => $user->rfc,
                'ine_number' => $user->ine_number,
                'street' => $user->street,
                'exterior_number' => $user->exterior_number,
                'interior_number' => $user->interior_number,
                'neighborhood' => $user->neighborhood,
                'zip_code' => $user->zip_code,
                'municipality' => $user->municipality,
                'state' => $user->state,
                'role' => $user->roles->pluck('name')->implode(', '),
            ])
            ->values();

        return Inertia::render('ServiceOrders/Anexo2', [
            'order' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number,
            ],
            'fields' => $fields,
            'users' => $users,
            'linked' => $serviceOrder->getMedia('anexo2')->isNotEmpty(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Genera el PDF del Anexo 2 con los datos editados y lo vincula a la orden.
     * POST /ordenes-servicio/{serviceOrder}/anexo-2/vincular
     */
    public function linkAnexo2(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:1000',
            'user_id' => 'nullable|integer',
        ]);

        $fields = array_merge([
            'fecha' => '',
            'num_solicitud' => '',
            'sol_nombre' => '', 'sol_calle' => '', 'sol_num_ext' => '', 'sol_num_int' => '',
            'sol_cp' => '', 'sol_colonia' => '', 'sol_municipio' => '', 'sol_estado' => '',
            'sol_telefono' => '', 'sol_correo' => '', 'sol_fax' => '',
            'con_nombre' => '', 'con_puesto' => '', 'con_calle' => '', 'con_num_ext' => '',
            'con_num_int' => '', 'con_cp' => '', 'con_colonia' => '', 'con_municipio' => '',
            'con_estado' => '', 'con_telefono' => '', 'con_correo' => '', 'con_fax' => '',
            'modalidad' => 'baja',
            'utilizacion' => 'centros',
            'rpu' => '',
            'nivel_tension' => '',
            'fecha_operacion' => '',
            'capacidad_bruta' => '',
            'capacidad_incrementar' => '',
            'generacion_promedio' => '',
            'manifiesto' => 'Si',
            'tecnologia' => 'solar',
            'tecnologia_otro' => '',
            'num_unidades' => '',
            'combustible_principal' => '',
            'combustible_secundario' => '',
            'firma_nombre' => '',
            'firma_cargo' => 'TITULAR',
            'firma_fecha' => '',
        ], $validated['fields']);

        for ($i = 1; $i <= 6; $i++) {
            $fields["utm_x{$i}"] = (string) ($fields["utm_x{$i}"] ?? '');
            $fields["utm_y{$i}"] = (string) ($fields["utm_y{$i}"] ?? '');
        }

        // Todo el texto del documento en mayúsculas
        $fields = $this->upperCaseFields($fields);

        $pdf = Pdf::loadView('pdf.anexo2', [
            'fields' => $fields,
            'order' => $serviceOrder,
            'generatedAt' => now(),
        ])->setPaper('letter');

        $fileName = 'anexo-2-orden-'.$serviceOrder->id.'.pdf';

        // Se reemplaza el anexo vinculado anterior (si existe) para no duplicar
        $serviceOrder->clearMediaCollection('anexo2');

        $tmpPath = tempnam(sys_get_temp_dir(), 'anexo2');
        file_put_contents($tmpPath, $pdf->output());

        try {
            $serviceOrder->addMedia($tmpPath)
                ->usingName('Anexo 2')
                ->usingFileName($fileName)
                ->withCustomProperties(['category' => 'anexo2'])
                ->toMediaCollection('anexo2');
        } finally {
            @unlink($tmpPath);
        }

        return response()->json([
            'success' => true,
            'linked' => true,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'message' => 'Anexo 2 vinculado a la orden de servicio.',
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
            'apply_interest' => 'nullable|boolean',
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
            // El comprobante SIEMPRE es obligatorio (se eliminó el pago rápido)
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        // Separar el interés moratorio del abono principal.
        // El interés NO descuenta el saldo: se registra aparte y se acumula.
        $baseAmount = (float) $installment->amount;
        $received = (float) $validated['amount'];
        $interestPortion = max(0, round($received - $baseAmount, 2));

        // Crear el pago real
        $payment = Payment::create([
            'branch_id' => $branchId,
            'client_id' => $serviceOrder->client_id,
            'service_order_id' => $serviceOrder->id,
            'installment_number' => $installment->installment_number,
            'amount' => $validated['amount'],
            'interest_amount' => $interestPortion,
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
            // El comprobante SIEMPRE es obligatorio (se eliminó el pago rápido)
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
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

        // Calcular el interés moratorio pendiente real (según apply_interest de cada cuota)
        $pendingInterestTotal = 0;
        foreach ($pendingInstallments as $inst) {
            $inst->recalculateStatus();
            if ($inst->apply_interest !== false) {
                $pendingInterestTotal += $inst->calculateInterest();
            }
        }
        $pendingInterestTotal = round($pendingInterestTotal, 2);

        // El excedente sobre el principal pendiente se registra como interés cobrado
        // (NO descuenta el saldo, solo se acumula como interés).
        $excess = max(0, (float) $validated['amount'] - $totalPending);
        $interestAmount = min($excess, $pendingInterestTotal);

        // Crear un solo pago por el total
        $payment = Payment::create([
            'branch_id' => $branchId,
            'client_id' => $serviceOrder->client_id,
            'service_order_id' => $serviceOrder->id,
            'amount' => $validated['amount'],
            'interest_amount' => $interestAmount,
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? 'Liquidación total',
        ]);

        if ($request->hasFile('proof')) {
            $payment->addMediaFromRequest('proof')->toMediaCollection('receipts');
        }

        // Distribuir el PRINCIPAL entre todas las cuotas pendientes
        $remainingAmount = (float) $validated['amount'] - $interestAmount;
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

    /**
     * API: Crea una cuota proyectada manual para plan Personalizado.
     * POST /api/service-orders/{serviceOrder}/installments
     */
    public function storeInstallment(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Solo permitido para plan Personalizado
        if ($serviceOrder->payment_method !== 'Personalizado') {
            return response()->json([
                'success' => false,
                'error' => 'Solo puedes agregar cuotas manuales en plan Personalizado.',
            ], 422);
        }

        $validated = $request->validate([
            'projected_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'label' => 'nullable|string|max:255',
            'apply_interest' => 'nullable|boolean',
        ]);

        // Obtener el último número de cuota
        $lastNumber = (int) $serviceOrder->paymentInstallments()->max('installment_number');

        $installment = $serviceOrder->paymentInstallments()->create([
            'installment_number' => $lastNumber + 1,
            'label' => $validated['label'] ?? "Proyección #" . ($lastNumber + 1),
            'projected_date' => $validated['projected_date'],
            'amount' => $validated['amount'],
            'apply_interest' => $validated['apply_interest'] ?? true,
            'status' => 'pending',
        ]);

        $installment->recalculateStatus();

        return response()->json([
            'success' => true,
            'installment' => $installment->fresh(),
            'message' => 'Cuota proyectada agregada correctamente.',
        ]);
    }

    /**
     * API: Elimina una cuota proyectada (solo plan Personalizado y sin pago).
     * DELETE /api/installments/{installment}
     */
    public function destroyInstallment(Request $request, PaymentInstallment $installment)
    {
        $serviceOrder = $installment->serviceOrder;
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Solo permitido para plan Personalizado
        if ($serviceOrder->payment_method !== 'Personalizado') {
            return response()->json([
                'success' => false,
                'error' => 'Solo puedes eliminar proyecciones en plan Personalizado.',
            ], 422);
        }

        // No permitir eliminar cuotas ya pagadas o vinculadas a un pago
        if ($installment->payment_id || in_array($installment->status, ['paid', 'on_time'])) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar una cuota ya pagada.',
            ], 422);
        }

        $installment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyección eliminada correctamente.',
        ]);
    }
}