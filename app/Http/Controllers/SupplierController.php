<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class SupplierController extends Controller
{
    /**
     * Muestra el listado de proveedores PERTENECIENTES a la sucursal actual.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        $search = $filters['search'] ?? null;

        // LÓGICA MULTI-TENANT: Obtenemos la sucursal actual (Sesión o Usuario)
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $suppliers = Supplier::query()
            ->where('branch_id', $branchId) // Filtro estricto por sucursal
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                      ->orWhere('contact_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'company_name' => $supplier->company_name,
                    'contact_name' => $supplier->contact_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'products_count' => $supplier->products()->count(),
                ];
            });

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:14',
        ]);

        // Inyectamos el branch_id automáticamente
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        // Verificación de seguridad básica (opcional si el middleware ya lo garantiza)
        if (!$branchId) {
            return back()->with('error', 'No se ha identificado la sucursal activa.');
        }

        $validated['branch_id'] = $branchId;

       $supplier = Supplier::create($validated);

        return redirect()->route('suppliers.show', $supplier->id)->with('success', 'Proveedor registrado exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para ver este proveedor.');
        }

        // Solo cargamos los productos que YA tiene el proveedor (Relación directa)
        // Esto es rápido y no sobrecarga la vista inicial.
        $assignedProducts = $supplier->products()
            ->with('media')
            ->select('products.id', 'products.name', 'products.sku', 'products.purchase_price as default_price')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image_url' => $product->getFirstMediaUrl('product_images'),
                    'supplier_sku' => $product->pivot->supplier_sku,
                    'purchase_price' => $product->pivot->purchase_price,
                    'currency' => $product->pivot->currency,
                    'delivery_days' => $product->pivot->delivery_days,
                ];
            });

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'assigned_products' => $assignedProducts,
            // NO enviamos available_products aquí para no alentar la carga
        ]);
    }

    /**
     * NUEVO MÉTODO: API Interna para cargar productos disponibles asíncronamente.
     */
    public function fetchAvailableProducts(Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Obtenemos IDs que ya tiene para excluirlos
        $assignedIds = $supplier->products()->pluck('products.id')->toArray();
        
        // Cargamos el catálogo restante (Heavy Load)
        $availableProducts = Product::whereNotIn('id', $assignedIds)
            ->with('media')
            ->select('id', 'name', 'sku', 'purchase_price', 'sale_price')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'purchase_price' => $product->purchase_price,
                    'sale_price' => $product->sale_price,
                    'image_url' => $product->getFirstMediaUrl('product_images'),
                ];
            });

        return response()->json($availableProducts);
    }

    public function edit(Supplier $supplier)
    {
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Supplier $supplier)
    {
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'No se puede eliminar el proveedor porque tiene Órdenes de Compra asociadas.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado.');
    }

    // -------------------------------------------------------------------------
    // MÉTODOS PARA ASIGNACIÓN DE PRODUCTOS
    // -------------------------------------------------------------------------

    public function assignProduct(Request $request, Supplier $supplier)
    {
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_price' => 'required|numeric|min:0',
            'currency' => 'required|string|in:MXN,USD',
            'supplier_sku' => 'nullable|string|max:100',
            'delivery_days' => 'required|integer|min:0',
        ]);

        $supplier->products()->syncWithoutDetaching([
            $validated['product_id'] => [
                'purchase_price' => $validated['purchase_price'],
                'currency' => $validated['currency'],
                'supplier_sku' => $validated['supplier_sku'],
                'delivery_days' => $validated['delivery_days'],
            ]
        ]);

        return back()->with('success', 'Producto asignado al catálogo del proveedor.');
    }

    public function detachProduct(Request $request, Supplier $supplier, Product $product)
    {
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        $supplier->products()->detach($product->id);
        
        return back()->with('success', 'Producto removido del proveedor.');
    }
}