<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WarehouseReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        // Filtro para ver pendientes o historial (por defecto: pendientes)
        $filterStatus = $request->input('status', 'pending');

        $query = ServiceOrder::with(['client:id,name', 'technician:id,name', 'items.product:id,name,sku'])
            ->where('branch_id', $branchId)
            ->whereIn('status', ['Completado', 'Facturado']); // Solo órdenes que ya se terminaron

        if ($filterStatus === 'pending') {
            $query->where('inventory_reconciled', false)
                  ->whereHas('items', function ($q) {
                      $q->whereNotNull('used_quantity'); // Asegurar que el técnico ya reportó
                  });
        } else {
            $query->where('inventory_reconciled', true);
        }

        $orders = $query->orderBy('completion_date', 'desc')->paginate(15)->withQueryString();

        // Calcular visualmente los sobrantes/faltantes antes de enviarlos a Vue
        $orders->getCollection()->transform(function ($order) {
            $itemsStatus = $order->items->map(function ($item) {
                $qty = (float) $item->quantity;
                $used = (float) $item->used_quantity;
                $diff = $qty - $used;
                
                return [
                    'id' => $item->id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'assigned' => $qty,
                    'used' => $used,
                    'difference' => $diff,
                    // Si diff > 0 (sobró, se devuelve). Si diff < 0 (faltó, se descuenta extra)
                    'type' => $diff > 0 ? 'sobrante' : ($diff < 0 ? 'faltante' : 'exacto')
                ];
            });

            return [
                'id' => $order->id,
                'client_name' => $order->client->name,
                'technician_name' => $order->technician->name ?? 'Sin Asignar',
                'completion_date' => $order->completion_date ? $order->completion_date->format('d/m/Y H:i') : 'N/A',
                'inventory_reconciled' => $order->inventory_reconciled,
                'notes' => $order->notes,
                'items_reconciliation' => $itemsStatus,
                'has_discrepancies' => $itemsStatus->contains(fn($i) => $i['difference'] !== 0.0)
            ];
        });

        return Inertia::render('Warehouse/Reconciliations/Index', [
            'orders' => $orders,
            'filterStatus' => $filterStatus
        ]);
    }

    public function approve(Request $request, ServiceOrder $serviceOrder)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        abort_if($serviceOrder->branch_id !== $branchId, 403, 'No tienes permiso en esta sucursal.');
        abort_if($serviceOrder->inventory_reconciled, 400, 'Esta orden ya fue conciliada previamente.');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:service_order_items,id',
            'items.*.used' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($serviceOrder, $validated) {
            // Mapeamos los items validados para un acceso más fácil
            $correctedItems = collect($validated['items'])->keyBy('id');

            foreach ($serviceOrder->items as $item) {
                // Verificar si el frontend nos mandó una corrección para este item
                if (!$correctedItems->has($item->id)) continue;

                $quantityAssigned = (float) $item->quantity;
                $quantityUsed = (float) $correctedItems[$item->id]['used']; // Usamos el valor corregido por almacén
                $difference = $quantityAssigned - $quantityUsed;

                // 1. Corregimos el historial de la orden con lo que el almacenista validó
                $item->update(['used_quantity' => $quantityUsed]);

                // 2. Ajustamos el almacén (Sobrantes o Faltantes)
                if ($difference > 0) {
                    InventoryService::addStock(
                        product: $item->product,
                        branchId: $serviceOrder->branch_id,
                        quantity: $difference,
                        reason: 'Devolución',
                        reference: $serviceOrder,
                        notes: "Sobrante conciliado de instalación. Orden #{$serviceOrder->id}"
                    );
                } elseif ($difference < 0) {
                    $extraNeeded = abs($difference);
                    InventoryService::removeStock(
                        product: $item->product,
                        branchId: $serviceOrder->branch_id,
                        quantity: $extraNeeded,
                        reason: 'Instalación',
                        reference: $serviceOrder,
                        notes: "Material extra utilizado en instalación. Orden #{$serviceOrder->id}"
                    );
                }
            }

            // Marcar como conciliado
            $serviceOrder->update(['inventory_reconciled' => true]);
        });

        return back()->with('success', 'Conciliación aprobada. Inventario ajustado correctamente.');
    }
}