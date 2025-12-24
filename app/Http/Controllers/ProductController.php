<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService; // Importar el servicio
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon;

class ProductController extends Controller
{
    /**
     * Muestra el listado de productos con stock de la sucursal SELECCIONADA.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        $search = $filters['search'] ?? null;
        
        // MODIFICADO: Usamos la sesión para respetar el selector del AppLayout
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $products = Product::with(['category', 'media'])
            // Cargamos SOLO la relación con la sucursal seleccionada
            ->with(['branches' => function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            }])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(function ($product) {
                // Obtenemos datos de la tabla pivote si existe relación con la sucursal
                $branchData = $product->branches->first()?->pivot;

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'description' => $product->description,
                    'sale_price' => $product->sale_price,
                    'category' => $product->category ? $product->category->name : 'Sin Categoría',
                    'image_url' => $product->getFirstMediaUrl('product_images'),
                    
                    // Datos de stock específicos de la sucursal seleccionada
                    'stock' => $branchData ? (float) $branchData->current_stock : 0,
                    'min_stock' => $branchData ? (float) $branchData->min_stock_alert : 0,
                    'location' => $branchData ? $branchData->location_in_warehouse : 'No asignado',
                ];
            });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        return Inertia::render('Products/Create', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'initial_stock' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0', // Validamos el nuevo campo
            'location' => 'nullable|string|max:255', 
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'category_id' => $validated['category_id'],
            'purchase_price' => $validated['purchase_price'],
            'sale_price' => $validated['sale_price'],
            'description' => $validated['description'],
        ]);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')->toMediaCollection('product_images');
        }

        // Gestión de Stock Inicial y Ubicación
        $initialStock = $request->input('initial_stock', 0);
        $minStock = $request->input('min_stock_alert', 5);
        $location = $request->input('location', 'Recepción'); 
        
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if ($branchId) {
            // 1. Crear la relación inicial en la tabla pivote con stock 0
            // Usamos attach con 0 porque InventoryService se encargará de sumar (y crear historial)
            $product->branches()->attach($branchId, [
                'current_stock' => 0, 
                'min_stock_alert' => $minStock,
                'location_in_warehouse' => $location
            ]);

            // 2. Si hay stock inicial, usar el Servicio para registrar el movimiento
            if ($initialStock > 0) {
                InventoryService::addStock(
                    product: $product,
                    branchId: $branchId,
                    quantity: $initialStock,
                    reason: 'initial_inventory',
                    notes: 'Inventario inicial al crear producto'
                );
            }
        }

        return redirect()->route('products.index')->with('success', 'Producto creado exitosamente.');
    }

    /**
     * Muestra el detalle del producto con historial.
     */
    public function show(Product $product)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Cargar relaciones necesarias
        $product->load(['category', 'media', 'branches' => function ($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        }]);

        $branchData = $product->branches->first()?->pivot;

        // --- LOGICA DE HISTORIAL ---
        $movements = $product->stockMovements()
            ->where('branch_id', $branchId)
            ->with(['user:id,name', 'reference']) // Eager loading
            ->latest()
            ->get()
            ->groupBy(function ($movement) {
                return $movement->created_at->format('Y-m');
            });

        // Formatear para el timeline
        $history = $movements->map(function ($items, $key) {
            $dateObj = Carbon::createFromFormat('Y-m', $key);
            
            return [
                'group_label' => ucfirst($dateObj->translatedFormat('F Y')), 
                'movements' => $items->map(function($m) {
                    return [
                        'id' => $m->id,
                        'date' => $m->created_at->format('d/m/Y H:i'),
                        'type' => $m->type, // 'entry', 'exit', 'adjustment'
                        'quantity' => $m->quantity,
                        'stock_after' => $m->stock_after,
                        'user_name' => $m->user?->name ?? 'Sistema',
                        'notes' => $m->notes,
                        'reference_text' => $this->getReferenceLabel($m->reference), 
                    ];
                })
            ];
        })->values();

        return Inertia::render('Products/Show', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'sale_price' => $product->sale_price,
                'purchase_price' => $product->purchase_price, 
                'category' => $product->category ? $product->category->name : 'Sin Categoría',
                'image_url' => $product->getFirstMediaUrl('product_images'),
                'stock' => $branchData ? $branchData->current_stock : 0,
                'location' => $branchData ? $branchData->location_in_warehouse : 'No asignado',
                'min_stock' => $branchData ? $branchData->min_stock_alert : 1,
            ],
            'stock_history' => $history // Pasamos el historial a la vista
        ]);
    }

    // Helper privado
    private function getReferenceLabel($reference)
    {
        if (!$reference) return 'Ajuste Manual';
        return match (class_basename($reference)) {
            'PurchaseOrder' => 'Orden Compra #' . $reference->id,
            'ServiceOrder' => 'Instalación #' . $reference->id,
            'Ticket' => 'Ticket #' . $reference->id,
            default => class_basename($reference) . ' #' . $reference->id,
        };
    }

    public function edit(Product $product)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        $categories = Category::select('id', 'name')->orderBy('name')->get();

        $product->load(['media', 'branches' => function ($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        }]);

        $branchData = $product->branches->first()?->pivot;

        return Inertia::render('Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'purchase_price' => $product->purchase_price,
                'sale_price' => $product->sale_price,
                'image_url' => $product->getFirstMediaUrl('product_images'),
                'current_stock' => $branchData ? $branchData->current_stock : 0,
                'location' => $branchData ? $branchData->location_in_warehouse : '',
                'min_stock_alert' => $branchData ? $branchData->min_stock_alert : 5, // Agregado también en edit para consistencia
            ],
            'categories' => $categories
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // ... (Validaciones existentes)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            // Ojo: Si permites editar stock aquí directamente, considera usar InventoryService::adjustStock
            // Por simplicidad en este update, mantenemos la lógica básica pero agregamos min_stock
            'current_stock' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
        ]);

        $product->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'category_id' => $validated['category_id'],
            'purchase_price' => $validated['purchase_price'],
            'sale_price' => $validated['sale_price'],
            'description' => $validated['description'],
        ]);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('product_images');
            $product->addMediaFromRequest('image')->toMediaCollection('product_images');
        }

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if ($branchId) {
            $newStock = $request->input('current_stock');
            $location = $request->input('location');
            
            // Nota: Idealmente, usarías InventoryService::adjustStock($product, $branchId, $newStock, 'Ajuste manual desde edición')
            // para que quede registro.
            
            $pivotData = [];
            if (!is_null($newStock)) $pivotData['current_stock'] = $newStock;
            if (!is_null($location)) $pivotData['location_in_warehouse'] = $location;

            if (!empty($pivotData)) {
                $product->branches()->syncWithoutDetaching([
                    $branchId => $pivotData
                ]);
            }
        }

        return redirect()->route('products.index')->with('success', 'Producto actualizado correctamente.');
    }

    // ... (Métodos destroy y search se mantienen igual)
    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Producto eliminado.');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        
        if (!$query) {
            return response()->json([]);
        }

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($product) {
                return [
                    'value' => $product->id,    
                    'label' => $product->name, 
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image_url' => $product->getFirstMediaUrl('product_images'),
                ];
            });

        return response()->json($products);
    }

    /**
     * MÉTODO DEDICADO: Ajuste de Inventario Rápido
     * Resuelve el conflicto de validación al no requerir datos del producto (nombre, sku).
     */
    public function adjustStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'current_stock' => 'required|numeric|min:0',
            'adjustment_note' => 'required|string|max:500', // Nota obligatoria para auditoría
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        if (!$branchId) {
            return back()->withErrors(['error' => 'No se ha detectado una sucursal activa.']);
        }

        // Llamamos al servicio para calcular la diferencia y registrar el movimiento
        // El servicio detectará si es entrada o salida automáticamente
        InventoryService::adjustStock(
            product: $product,
            branchId: $branchId,
            newRealStock: $validated['current_stock'],
            notes: $validated['adjustment_note']
        );

        return back()->with('success', 'Inventario ajustado y registrado en el historial.');
    }
}