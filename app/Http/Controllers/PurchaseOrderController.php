<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\InventoryService;
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
            ->withCount('items')
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
                    'supplier' => $order->supplier,
                    'requested_by' => $order->requestor ? $order->requestor->name : 'Sistema',
                    'status' => $order->status,
                    'total_cost' => $order->total_cost,
                    'expected_date' => $order->expected_date ? $order->expected_date->format('Y-m-d') : null,
                    'received_date' => $order->received_date ? $order->received_date->format('Y-m-d') : null, // <--- Agregado
                    'created_at' => $order->created_at->format('Y-m-d'),
                    'items_count' => $order->items_count,
                    'notes' => $order->notes,
                    'currency' => $order->currency,
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

        $suppliers = Supplier::where('branch_id', $branchId)
            ->select('id', 'company_name')
            ->orderBy('company_name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers
        ]);
    }

    /**
     * API: Obtiene los productos asignados a un proveedor.
     */
    public function getSupplierProducts(Supplier $supplier)
    {
        return response()->json(
            $supplier->products()
                ->with('media') 
                ->select('products.id', 'products.name', 'products.sku') 
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'image_url' => $product->getFirstMediaUrl() ?: null,
                        'purchase_price' => $product->pivot->purchase_price,
                        'currency' => $product->pivot->currency,
                        'supplier_sku' => $product->pivot->supplier_sku,
                        'delivery_days' => $product->pivot->delivery_days,
                    ];
                })
        );
    }

    /**
     * Almacena una nueva orden de compra.
     */
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $branchId) {
            $order = PurchaseOrder::create([
                'branch_id' => $branchId,
                'supplier_id' => $validated['supplier_id'],
                'requested_by' => Auth::id(),
                'status' => 'Borrador',
                'expected_date' => $validated['expected_date'],
                'notes' => $validated['notes'],
                'currency' => $validated['currency'],
                'total_cost' => 0,
            ]);

            $totalCost = 0;

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

        // Cargamos relaciones necesarias
        $purchaseOrder->load(['supplier', 'items.product.media', 'requestor']);

        // Transformamos los items para facilitar el acceso a la imagen en el Vue
        $items = $purchaseOrder->items->map(function ($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'subtotal' => $item->quantity * $item->unit_cost,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    // Obtenemos la URL de la imagen de Spatie
                    'image_url' => $product->getFirstMediaUrl('product_images') ?: $product->getFirstMediaUrl() ?: null,
                ]
            ];
        });

        // Reemplazamos la colección de items con la transformada o la pasamos aparte
        // Para no romper la estructura del modelo, crearemos una estructura limpia para Inertia
        $orderData = [
            'id' => $purchaseOrder->id,
            'status' => $purchaseOrder->status,
            'created_at' => $purchaseOrder->created_at,
            'expected_date' => $purchaseOrder->expected_date,
            'received_date' => $purchaseOrder->received_date,
            'notes' => $purchaseOrder->notes,
            'currency' => $purchaseOrder->currency,
            'total_cost' => $purchaseOrder->total_cost,
            'supplier' => $purchaseOrder->supplier,
            'requestor' => $purchaseOrder->requestor,
            'items' => $items, // Usamos los items procesados
        ];

        return Inertia::render('Purchases/Show', [
            'order' => $orderData
        ]);
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        if (in_array($purchaseOrder->status, ['Recibida', 'Cancelada'])) {
            return back()->with('error', 'No se puede editar una orden procesada o cancelada.');
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

        if (in_array($purchaseOrder->status, ['Recibida', 'Cancelada'])) {
            return back()->with('error', 'Orden bloqueada por su estado actual.');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'expected_date' => $validated['expected_date'],
                'notes' => $validated['notes'],
                'currency' => $validated['currency'],
            ]);

            $purchaseOrder->items()->delete();

            $totalCost = 0;
            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_cost'];
                $totalCost += $subtotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);
            }
            
            $purchaseOrder->update(['total_cost' => $totalCost]);
        });

        return redirect()->route('purchases.index')->with('success', 'Orden actualizada.');
    }

    /**
     * CAMBIO DE ESTADO (Manejo de Stock).
     */
    public function changeStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);
        
        $request->validate([
            'status' => 'required|in:Solicitada,Recibida,Cancelada,Borrador'
        ]);

        $newStatus = $request->status;
        $oldStatus = $purchaseOrder->status;

        if ($oldStatus === 'Recibida') {
            return back()->with('error', 'Esta orden ya fue recibida.');
        }

        if ($oldStatus === 'Cancelada' && $newStatus === 'Recibida') {
            return back()->with('error', 'No puedes recibir una orden cancelada directamente.');
        }

        DB::transaction(function () use ($purchaseOrder, $newStatus, $oldStatus) {
            
            // --- MOVIMIENTO DE STOCK (ENTRADA) ---
            if ($newStatus === 'Recibida' && $oldStatus !== 'Recibida') {
                $purchaseOrder->load('items.product');
                
                // 1. Asignar fecha de recepción
                $purchaseOrder->received_date = now(); // <--- ACTUALIZACIÓN AQUÍ
                
                // 2. Aumentar Stock
                foreach ($purchaseOrder->items as $item) {
                    InventoryService::addStock(
                        product: $item->product,
                        branchId: $purchaseOrder->branch_id,
                        quantity: $item->quantity,
                        reason: 'Compra',
                        reference: $purchaseOrder,
                        notes: "Recepción de Orden de Compra #{$purchaseOrder->id}"
                    );
                }
            } else {
                // Si cambiamos a otro estado y tenía fecha, la limpiamos?
                // Depende de la lógica, por seguridad la limpiamos si se regresa a borrador
                if ($newStatus === 'Borrador') {
                    $purchaseOrder->received_date = null;
                }
            }

            $purchaseOrder->status = $newStatus;
            $purchaseOrder->save();
        });

        return back()->with('success', "Estado actualizado a: {$newStatus}");
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        if ($purchaseOrder->status !== 'Borrador') {
            return back()->with('error', 'Solo se pueden eliminar órdenes en estado Borrador.');
        }

        $purchaseOrder->delete(); 

        return redirect()->route('purchases.index')->with('success', 'Orden eliminada.');
    }

    /**
     * Muestra la vista de impresión moderna.
     */
    public function printOrder(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAction($purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.product.media', 'requestor']);

        // Misma transformación para asegurar imágenes
        $items = $purchaseOrder->items->map(function ($item) {
            $product = $item->product;
            return [
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'subtotal' => $item->quantity * $item->unit_cost,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_image' => $product->getFirstMediaUrl('product_images') ?: $product->getFirstMediaUrl() ?: null,
            ];
        });

        return Inertia::render('Purchases/Print', [
            'order' => [
                'id' => $purchaseOrder->id,
                'created_at' => $purchaseOrder->created_at->format('d/m/Y'),
                'expected_date' => $purchaseOrder->expected_date ? $purchaseOrder->expected_date->format('d/m/Y') : 'N/A',
                'status' => $purchaseOrder->status,
                'currency' => $purchaseOrder->currency,
                'total_cost' => $purchaseOrder->total_cost,
                'notes' => $purchaseOrder->notes,
                'supplier' => $purchaseOrder->supplier,
                'items' => $items,
                'company_info' => [
                    // Puedes personalizar esto o sacarlo de una configuración global
                    'name' => config('app.name'), 
                    'address' => 'Calle Principal #123, Ciudad',
                    'phone' => '(555) 123-4567'
                ]
            ]
        ]);
    }

    private function authorizeAction($order)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($order->branch_id !== $branchId) {
            abort(403, 'No tienes permiso sobre esta orden.');
        }
    }
}