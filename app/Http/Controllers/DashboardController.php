<?php

namespace App\Http\Controllers;

use App\Models\Client;
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

        // 4. Clientes con Saldos
        $clientsWithBalance = Client::where('branch_id', $branchId)
            ->withSum(['serviceOrders as total_debt' => function($query) {
                $query->whereNotIn('status', ['Cotización', 'Cancelado']);
            }], 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->get()
            ->map(function ($client) {
                $balance = ($client->total_debt ?? 0) - ($client->total_paid ?? 0);
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone,
                    'balance' => $balance,
                ];
            })
            ->filter(function ($client) {
                return $client['balance'] > 0;
            })
            ->sortByDesc('balance')
            ->take(5)
            ->values();

        // 5. TAREAS SEMANALES (KANBAN PARA DASHBOARD)
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

        $weeklyTasks = $weeklyTasksQuery->get()->groupBy(function($task) {
            return Carbon::parse($task->start_date)->format('Y-m-d');
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

        return Inertia::render('Dashboard/Index', [
            'pendingServiceOrders' => $pendingServiceOrders,
            'lowStockProducts' => $lowStockProducts,
            'pendingPurchaseOrders' => $pendingPurchaseOrders,
            'clientsWithBalance' => $clientsWithBalance,
            'kpis' => $kpis,
            'weeklyTasks' => $weeklyTasks,
            'weekDays' => $weekDays,      
        ]);
    }
}