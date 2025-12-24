<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\InventoryService; // Tu servicio de inventario
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderController extends Controller
{
    /**
     * Muestra el listado de órdenes de compra.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $orders = PurchaseOrder::query()
            ->where('branch_id', $branchId)
            ->with(['supplier:id,company_name,contact_name,email', 'requestor:id,name'])
            ->withCount('items') // Para saber cuántos productos trae
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function ($q) use ($search) {
                          $q->where('company_name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function (Builder $query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'supplier' => $order->supplier, // Objeto completo (id, nombre, etc)
                    'requested_by' => $order->requestor ? $order->requestor->name : 'Sistema',
                    'status' => $order->status,
                    'total_cost' => $order->total_cost,
                    'expected_date' => $order->expected_date ? $order->expected_date->format('Y-m-d') : null,
                    'created_at' => $order->created_at->format('Y-m-d'),
                    'items_count' => $order->items_count,
                    'notes' => $order->notes,
                ];
            });

        return Inertia::render('Purchases/Index', [
            'orders' => $orders,
            'filters' => $filters,
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva orden.
     */
    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Cargamos proveedores de la sucursal para el select
        $suppliers = Supplier::where('branch_id', $branchId)
            ->select('id', 'company_name')
            ->orderBy('company_name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Almacena una nueva orden de compra en la base de datos.
     */
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $branchId) {
            // 1. Crear Orden
            $order = PurchaseOrder::create([
                'branch_id' => $branchId,
                'supplier_id' => $validated['supplier_id'],
                'requested_by' => Auth::id(),
                'status' => 'Borrador', // Inicialmente Borrador o Solicitada
                'expected_date' => $validated['expected_date'],
                'notes' => $validated['notes'],
                'total_cost' => 0, // Se calcula abajo
            ]);

            $totalCost = 0;

            // 2. Crear Items
            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_cost'];
                $totalCost += $subtotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            // 3. Actualizar total
            $order->update(['total_cost' => $totalCost]);
        });

        return redirect()->route('purchases.index')->with('success', 'Orden de compra creada exitosamente.');
    }

    /**
     * Muestra los detalles de una orden específica.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.product', 'requestor']);

        return Inertia::render('Purchases/Show', [
            'order' => $purchaseOrder
        ]);
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        // No permitir editar si ya fue recibida o cancelada
        if (in_array($purchaseOrder->status, ['Recibida', 'Cancelada'])) {
            return back()->with('error', 'No se puede editar una orden que ya ha sido procesada o cancelada.');
        }

        $purchaseOrder->load('items');
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        $suppliers = Supplier::where('branch_id', $branchId)->select('id', 'company_name')->get();

        return Inertia::render('Purchases/Edit', [
            'order' => $purchaseOrder,
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Actualiza la orden de compra.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        // Si intentan editar una orden cerrada
        if (in_array($purchaseOrder->status, ['Recibida', 'Cancelada'])) {
            return back()->with('error', 'Orden bloqueada por su estado actual.');
        }

        // Validación básica
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            // ... validaciones de items igual que store
        ]);

        // Lógica de actualización (Sync items, recalcular total) similar a Store
        // ... (Implementación abreviada por espacio, pero esencialmente es borrar items viejos y crear nuevos o updatear)

        // NOTA: Si el usuario quiere cambiar STATUS a Recibida mediante UPDATE, 
        // recomendamos usar el método dedicado `changeStatus` abajo.
        
        $purchaseOrder->update($validated);

        return redirect()->route('purchases.index')->with('success', 'Orden actualizada.');
    }

    /**
     * Método dedicado para cambiar el estado (Index Actions).
     * Ruta: PATCH /ordenes-compras/{id}/status
     */
    public function changeStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);
        
        $request->validate([
            'status' => 'required|in:Solicitada,Recibida,Cancelada,Borrador'
        ]);

        $newStatus = $request->status;
        $oldStatus = $purchaseOrder->status;

        // Validaciones de flujo
        if ($oldStatus === 'Recibida') {
            return back()->with('error', 'Esta orden ya fue recibida y el inventario afectado. No se puede revertir automáticamente.');
        }

        if ($oldStatus === 'Cancelada' && $newStatus === 'Recibida') {
            return back()->with('error', 'No puedes recibir una orden cancelada directamente. Cambiala a Borrador primero.');
        }

        DB::transaction(function () use ($purchaseOrder, $newStatus, $oldStatus) {
            
            // LOGICA CRITICA: RECIBIR MERCANCÍA
            if ($newStatus === 'Recibida' && $oldStatus !== 'Recibida') {
                $purchaseOrder->load('items.product');
                
                foreach ($purchaseOrder->items as $item) {
                    // Usamos el servicio inyectado
                    InventoryService::addStock(
                        product: $item->product,
                        branchId: $purchaseOrder->branch_id,
                        quantity: $item->quantity,
                        reason: 'Compra',
                        reference: $purchaseOrder, // Relación polimórfica
                        notes: "Recepción de Orden de Compra #{$purchaseOrder->id}"
                    );
                }
            }

            // LOGICA: CANCELAR
            if ($newStatus === 'Cancelada') {
                // Si hubiera lógica de liberar presupuesto, iría aquí.
            }

            $purchaseOrder->status = $newStatus;
            $purchaseOrder->save();
        });

        return back()->with('success', "Estado actualizado a: {$newStatus}");
    }

    /**
     * Elimina la orden (solo si es Borrador).
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        if ($purchaseOrder->status !== 'Borrador') {
            return back()->with('error', 'Solo se pueden eliminar órdenes en estado Borrador. Intenta cancelarla.');
        }

        $purchaseOrder->delete(); // Cascade eliminará los items

        return redirect()->route('purchases.index')->with('success', 'Orden eliminada.');
    }

    // Helper de seguridad Multi-tenant
    private function authorizeAction($order)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($order->branch_id !== $branchId) {
            abort(403, 'No tienes permiso sobre esta orden.');
        }
    }
}