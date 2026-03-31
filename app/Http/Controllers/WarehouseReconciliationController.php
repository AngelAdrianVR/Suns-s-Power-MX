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

        // Quitamos la restricción estricta del estatus de la orden para mostrar TODAS.
        $query = ServiceOrder::with(['client:id,name', 'technician:id,name', 'items.product:id,name,sku'])
            ->where('branch_id', $branchId);

        if ($filterStatus === 'pending') {
            // Buscamos las que NO están conciliadas, que SÍ tengan productos y que 
            // NINGÚN producto esté pendiente de ser reportado (used_quantity = null)
            $query->where('inventory_reconciled', false)
                  ->whereHas('items') 
                  ->whereDoesntHave('items', function ($q) {
                      $q->whereNull('used_quantity'); 
                  });
        } else {
            // Historial de conciliados
            $query->where('inventory_reconciled', true);
        }

        $orders = $query->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();

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
                'status' => $order->status, // <-- Agregamos el estatus
                'client_name' => $order->client->name,
                'technician_name' => $order->technician->name ?? 'Sin Asignar',
                'completion_date' => $order->completion_date ? $order->completion_date->format('d/m/Y H:i') : 'En curso',
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
                $item->used_quantity = $quantityUsed;
                $item->save();

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

            // Marcar como conciliado mediante asignación directa para evadir la restricción de $fillable
            $serviceOrder->inventory_reconciled = true;
            $serviceOrder->save();
        });

        return back()->with('success', 'Conciliación aprobada. Inventario ajustado correctamente.');
    }
}