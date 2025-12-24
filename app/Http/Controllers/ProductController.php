<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

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
        $location = $request->input('location', 'Recepción'); 
        
        // MODIFICADO: Usamos la sesión actual
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if ($branchId) {
            $product->branches()->attach($branchId, [
                'current_stock' => $initialStock,
                'min_stock_alert' => 5, // Default
                'location_in_warehouse' => $location
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Producto creado exitosamente.');
    }

    /**
     * Muestra el detalle del producto.
     */
    public function show(Product $product)
    {
        // MODIFICADO: Contexto dinámico
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // Cargar relaciones necesarias
        $product->load(['category', 'media', 'branches' => function ($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        }]);

        $branchData = $product->branches->first()?->pivot;

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
                // Datos de sucursal
                'stock' => $branchData ? $branchData->current_stock : 0,
                'location' => $branchData ? $branchData->location_in_warehouse : 'No asignado',
                'min_stock' => $branchData ? $branchData->min_stock_alert : 1,
            ]
        ]);
    }

    public function edit(Product $product)
    {
        // MODIFICADO: Contexto dinámico
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $categories = Category::select('id', 'name')->orderBy('name')->get();

        // Cargar media y datos de la sucursal actual
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
                // Stock y ubicación actual en la sucursal del usuario
                'current_stock' => $branchData ? $branchData->current_stock : 0,
                'location' => $branchData ? $branchData->location_in_warehouse : '',
            ],
            'categories' => $categories
        ]);
    }

    /**
     * Actualiza el producto en la base de datos.
     */
    public function update(Request $request, Product $product)
    {
        // Validación
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'current_stock' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
        ]);

        // Actualizar datos básicos
        $product->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'category_id' => $validated['category_id'],
            'purchase_price' => $validated['purchase_price'],
            'sale_price' => $validated['sale_price'],
            'description' => $validated['description'],
        ]);

        // Gestionar imagen
        if ($request->hasFile('image')) {
            $product->clearMediaCollection('product_images');
            $product->addMediaFromRequest('image')->toMediaCollection('product_images');
        }

        // Actualizar stock y ubicación de la sucursal actual
        $newStock = $request->input('current_stock');
        $location = $request->input('location');
        
        // MODIFICADO: Contexto dinámico
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if ($branchId) {
            // Preparamos los datos a sincronizar
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

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Producto eliminado.');
    }

    /**
     * API para buscar productos (Dropdown en Show).
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        
        if (!$query) {
            return response()->json([]);
        }

        // Buscamos por nombre o SKU
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
}