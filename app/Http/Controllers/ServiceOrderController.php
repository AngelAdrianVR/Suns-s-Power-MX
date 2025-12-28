<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class ServiceOrderController extends Controller
{
    /**
     * Muestra el listado de órdenes de servicio (Dashboard Operativo).
     */
    public function index(Request $request)
    {
        // 1. Contexto Multi-tenant
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // 2. Filtros
        $filters = $request->only(['search', 'status', 'date_range']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        // 3. Consulta Base
        $orders = ServiceOrder::query()
            ->with([
                'client:id,name,branch_id', // Solo campos necesarios
                'technician:id,name,profile_photo_path',
                'salesRep:id,name'
            ])
            ->withCount('tasks') // Para calcular progreso rápido sin cargar todas las tareas
            ->where('branch_id', $branchId)
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
            ->through(function ($order) {
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
                        'photo' => $order->technician->profile_photo_path,
                    ] : null,
                    'sales_rep' => $order->salesRep->name ?? 'N/A',
                    'total_amount' => $order->total_amount,
                    // Usamos el accessor del modelo o cálculo manual
                    'progress' => $order->progress ?? 0, 
                    'created_at_human' => $order->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('ServiceOrders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            // Enviamos los estados posibles para un dropdown de filtro
            'statuses' => ['Cotización', 'Aceptado', 'En Proceso', 'Instalado', 'Facturado', 'Cancelado']
        ]);
    }

    /**
     * Prepara la vista de creación.
     */
    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        return Inertia::render('ServiceOrders/Create', [
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            // Filtramos usuarios técnicos y vendedores de esta sucursal
            'technicians' => User::where('branch_id', $branchId)->where('is_active', true)->get(['id', 'name']), 
            'sales_reps' => User::where('branch_id', $branchId)->where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Almacena la nueva orden.
     */
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'technician_id' => 'nullable|exists:users,id',
            'sales_rep_id' => 'required|exists:users,id',
            'status' => 'required|in:Cotización,Aceptado,En Proceso,Instalado,Facturado,Cancelado',
            'start_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'installation_address' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $validated['branch_id'] = $branchId;

        DB::transaction(function () use ($validated) {
            $order = ServiceOrder::create($validated);
        });

        return redirect()->route('service-orders.index')
            ->with('success', 'Orden de servicio generada correctamente.');
    }

    /**
     * Muestra el detalle completo y prepara datos para el diagrama dinámico.
     */
    public function show(ServiceOrder $serviceOrder)
    {
        // Seguridad Tenant
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) abort(403);

        // Cargar todas las relaciones necesarias para el "Expediente de Obra"
        $serviceOrder->load([
            'client',
            'technician',
            'items.product',
            'tasks.assignees', // <--- CORREGIDO: Usamos 'assignees'
            'documents',
            'payments'
        ]);

        // Transformación de datos para el Gráfico/Diagrama (Gantt o Timeline)
        $diagramData = $serviceOrder->tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->title,
                'start' => $task->start_date?->format('Y-m-d H:i:s'),
                'end' => $task->due_date?->format('Y-m-d H:i:s'),
                'status' => $task->status,
                'progress' => $task->status === 'Completado' ? 100 : ($task->status === 'En Proceso' ? 50 : 0),
                'dependencies' => [], 
                // CORREGIDO: Usamos $task->assignees en lugar de $task->users
                'assignees' => $task->assignees->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->profile_photo_path
                ])
            ];
        });

        // OBTENER USUARIOS PARA EL SELECT DE ASIGNACIÓN (Modal de Tareas)
        $assignableUsers = User::where('branch_id', $branchId)
             ->where('is_active', true)
             ->select('id', 'name')
             ->orderBy('name')
             ->get();

        return Inertia::render('ServiceOrders/Show', [
            'order' => $serviceOrder,
            'diagram_data' => $diagramData, // Datos listos para el componente visual
            'stats' => [
                'total_tasks' => $serviceOrder->tasks->count(),
                'completed_tasks' => $serviceOrder->tasks->where('status', 'Completado')->count(),
                'pending_balance' => $serviceOrder->total_amount - $serviceOrder->payments->sum('amount')
            ],
            'assignable_users' => $assignableUsers // Lista de usuarios para el modal
        ]);
    }

    /**
     * Edición de la orden.
     */
    public function edit(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) abort(403);

        return Inertia::render('ServiceOrders/Edit', [
            'order' => $serviceOrder,
            'technicians' => User::where('branch_id', $branchId)->get(['id', 'name']),
        ]);
    }

    /**
     * Actualización.
     */
    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) abort(403);

        $validated = $request->validate([
            'status' => 'required|string',
            'start_date' => 'nullable|date',
            'completion_date' => 'nullable|date|after_or_equal:start_date',
            'technician_id' => 'nullable|exists:users,id',
            'installation_address' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $serviceOrder->update($validated);

        return redirect()->route('service-orders.show', $serviceOrder->id)
            ->with('success', 'Orden actualizada.');
    }

    /**
     * Eliminación con validación de integridad.
     */
    public function destroy(ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($serviceOrder->branch_id !== $branchId) abort(403);

        if ($serviceOrder->status === 'Instalado' || $serviceOrder->status === 'Facturado') {
            return back()->with('error', 'No se puede eliminar una orden completada o facturada.');
        }

        $serviceOrder->delete(); 

        return redirect()->route('service-orders.index')->with('success', 'Orden eliminada.');
    }
}