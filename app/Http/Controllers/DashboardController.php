<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PaymentInstallment;
use App\Models\PortalPayment;
use App\Models\PurchaseOrder;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Importante para las consultas directas a pivote
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Recuperar el ID de la sucursal desde la sesión o del usuario
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // 1. Órdenes de Servicio Pendientes
        $pendingServiceOrders = ServiceOrder::with(['client:id,name', 'technician:id,name'])
            ->where('branch_id', $branchId)
            ->whereIn('status', ['Cotización', 'Aceptado', 'En Proceso'])
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'client_name' => $order->client->name,
                    'status' => $order->status,
                    'start_date' => $order->start_date ? $order->start_date->format('d/m/Y') : 'Por programar',
                    'technician' => $order->technician ? $order->technician->name : 'Sin asignar',
                    'total_amount' => $order->total_amount,
                ];
            });

        // 2. Productos con Stock Bajo
        $lowStockProducts = DB::table('branch_product')
            ->join('products', 'branch_product.product_id', '=', 'products.id')
            ->where('branch_product.branch_id', $branchId)
            ->whereRaw('branch_product.current_stock <= branch_product.min_stock_alert')
            ->select(
                'products.id',
                'products.sku',
                'products.name',
                'branch_product.current_stock',
                'branch_product.min_stock_alert'
            )
            ->orderBy('branch_product.current_stock', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'sku' => $row->sku,
                    'name' => $row->name,
                    'current_stock' => $row->current_stock,
                    'min_stock_alert' => $row->min_stock_alert,
                    'status' => $row->current_stock == 0 ? 'Agotado' : 'Bajo',
                ];
            });

        // 3. Órdenes de Compra por Recibir
        $pendingPurchaseOrders = PurchaseOrder::with('supplier:id,company_name')
            ->where('branch_id', $branchId)
            ->where('status', 'Solicitada')
            ->orderBy('expected_date', 'asc')
            ->take(5)
            ->get()
            ->map(function ($po) {
                return [
                    'id' => $po->id,
                    'supplier' => $po->supplier->company_name,
                    'expected_date' => $po->expected_date ? $po->expected_date->format('d/m/Y') : 'Sin fecha',
                    'total_cost' => $po->total_cost,
                    'currency' => $po->currency,
                ];
            });

        // 4. Clientes con Saldos (OPTIMIZADO EN SQL)
        $clientsWithBalance = Client::where('branch_id', $branchId)
            ->withSum(['serviceOrders as total_debt' => function($query) {
                $query->whereNotIn('status', ['Cotización', 'Cancelado']);
            }], 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->withSum('payments as total_interest', 'interest_amount')
            ->havingRaw('(IFNULL(total_debt, 0) - (IFNULL(total_paid, 0) - IFNULL(total_interest, 0))) > 0')
            ->get()
            ->map(function ($client) {
                $paid = ($client->total_paid ?? 0) - ($client->total_interest ?? 0);
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone,
                    'balance' => ($client->total_debt ?? 0) - $paid,
                ];
            })
            ->sortByDesc('balance')
            ->take(5)
            ->values();

        // 5. TAREAS SEMANALES (KANBAN PARA DASHBOARD) (OPTIMIZADO PARA PAYLOAD HTTP/2)
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay(); 

        $weekDays = [];
        for ($i = 0; $i <= 5; $i++) {
            $currentDay = $weekStart->copy()->addDays($i);
            $weekDays[] = [
                'date' => $currentDay->format('Y-m-d'),
                'day_name' => ucfirst($currentDay->locale('es')->isoFormat('dddd')),
                'day_number' => $currentDay->format('d'),
            ];
        }

        $weeklyTasksQuery = Task::with(['assignees', 'taskable', 'comments.user'])
            ->where('branch_id', $branchId)
            ->has('assignees')
            ->whereBetween('start_date', [$weekStart->startOfDay(), $weekEnd]);

        // Restricción: Si NO tiene view_all, solo ve las tareas donde esté asignado
        if (!Auth::user()->can('pms.view_all')) {
            $weeklyTasksQuery->whereHas('assignees', function($q) {
                $q->where('users.id', Auth::id());
            });
        }

        // Mapeo estricto antes de agrupar para evitar sobrecarga del servidor en Hostgator
        $weeklyTasks = $weeklyTasksQuery->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title ?? $task->name ?? '', // Ajusta a la columna real de tu base de datos
                'description' => $task->description,
                'start_date' => $task->start_date,
                'due_date' => $task->due_date,
                'status' => $task->status,
                'priority' => $task->priority ?? null, 
                
                'assignees' => $task->assignees->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_photo_url' => $user->profile_photo_url, 
                    ];
                }),
                
                'taskable' => $task->taskable ? [
                    'id' => $task->taskable->id,
                    'type' => class_basename($task->taskable_type),
                ] : null,
                
                'comments' => $task->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'created_at' => $comment->created_at->format('Y-m-d H:i'),
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                        ]
                    ];
                }),
            ];
        })->groupBy(function($task) {
            return Carbon::parse($task['start_date'])->format('Y-m-d');
        });

        // Resumen General (KPIs rápidos)
        $kpis = [
            'total_pending_services' => ServiceOrder::where('branch_id', $branchId)->where('status', 'En Proceso')->count(),
            
            'total_low_stock' => DB::table('branch_product')
                ->where('branch_id', $branchId)
                ->whereRaw('current_stock <= min_stock_alert')
                ->count(),

            'monthly_sales' => Auth::user()->can('sales.view_sales_amount') 
                ? ServiceOrder::where('branch_id', $branchId)
                    ->whereNotIn('status', ['Cotización', 'Cancelado'])
                    ->whereMonth('created_at', now()->month)
                    ->sum('total_amount')
                : 0,
        ];

        // 6. PAGOS PRÓXIMOS / VENCIDOS
        $upcomingPayments = PaymentInstallment::with([
                'serviceOrder.client.contacts',
                'serviceOrder:id,client_id,branch_id,total_amount,payment_method'
            ])
            ->whereHas('serviceOrder', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->whereNotIn('status', ['Cancelado', 'Cotización']);
            })
            ->whereNull('payment_id')
            ->whereNotIn('status', ['paid', 'on_time'])
            ->where(function ($q) {
                $q->whereBetween('projected_date', [now()->subDays(60), now()->addDays(10)])
                  ->orWhere('projected_date', '>=', now()->subDays(60));
            })
            ->orderBy('projected_date', 'asc')
            ->take(30)
            ->get()
            ->map(function ($inst) {
                $projDate = Carbon::parse($inst->projected_date)->startOfDay();
                $daysDiff = (int) $projDate->diffInDays(now()->startOfDay(), false);
                $isOverdue = $daysDiff >= 0;
                $client = $inst->serviceOrder->client ?? null;
                $primaryContact = $client?->contacts->firstWhere('is_primary', true) ?? $client?->contacts->first();

                return [
                    'id' => $inst->id,
                    'installment' => $inst->installment_number,
                    'label' => $inst->label,
                    'amount' => (float) $inst->amount,
                    'projected_date' => $inst->projected_date->format('Y-m-d'),
                    'status' => $inst->status,
                    'days_until_due' => $isOverdue ? -$daysDiff : -$daysDiff,
                    'is_overdue' => $isOverdue,
                    'days_abs' => abs($daysDiff),
                    'client' => $client ? [
                        'id' => $client->id,
                        'name' => $client->name,
                    ] : null,
                    'service_order_id' => $inst->service_order_id,
                    'service_order_total' => (float) ($inst->serviceOrder->total_amount ?? 0),
                    'has_email' => $primaryContact && !empty($primaryContact->email),
                    'has_phone' => $primaryContact && !empty($primaryContact->phone),
                    'contact_email' => $primaryContact->email ?? null,
                    'contact_phone' => $primaryContact->phone ?? null,
                ];
            })
            ->filter(fn($p) => $p['client'] !== null)
            ->values();

        // 7. ABONOS DEL PORTAL DE CLIENTES PENDIENTES DE VALIDACIÓN
        // Solo se carga el payload para usuarios con permiso validar_abonos.
        $pendingPortalPayments = collect();

        if (Auth::user()->can('validar_abonos')) {
            $pendingPortalPayments = PortalPayment::with(['client:id,name', 'serviceOrder:id,service_number'])
                ->where('status', PortalPayment::STATUS_IN_REVIEW)
                ->where('branch_id', $branchId)
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (PortalPayment $p) => [
                    'id' => $p->id,
                    'client_id' => $p->client?->id,
                    'client_name' => $p->client?->name,
                    'service_number' => $p->serviceOrder?->service_number,
                    'amount' => (float) $p->amount,
                    'payment_date' => $p->payment_date?->format('d/m/Y'),
                    'method' => $p->method,
                    'reference' => $p->reference,
                    'notes' => $p->notes,
                    'created_at' => $p->created_at?->format('d/m/Y H:i'),
                    'receipt' => (($media = $p->getFirstMedia('receipts')) !== null)
                        ? ['url' => $media->getUrl(), 'name' => $media->file_name]
                        : null,
                ])
                ->values();
        }

        return Inertia::render('Dashboard/Index', [
            'pendingServiceOrders' => $pendingServiceOrders,
            'lowStockProducts' => $lowStockProducts,
            'pendingPurchaseOrders' => $pendingPurchaseOrders,
            'clientsWithBalance' => $clientsWithBalance,
            'kpis' => $kpis,
            'weeklyTasks' => $weeklyTasks,
            'weekDays' => $weekDays,
            'upcomingPayments' => $upcomingPayments,
            'pendingPortalPayments' => $pendingPortalPayments,
        ]);
    }
}