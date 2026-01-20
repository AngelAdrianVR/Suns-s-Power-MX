<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Muestra el listado de proveedores GLOBAL (Todas las sucursales).
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        $search = $filters['search'] ?? null;
        
        // Eliminamos el filtro de branch_id para que sean globales
        // $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $suppliers = Supplier::query()
            // ->where('branch_id', $branchId) // COMENTADO: Ahora es global
            ->withCount('contacts') 
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                      ->orWhereHas('contacts', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(function ($supplier) {
                $mainContact = $supplier->mainContact; 
                
                return [
                    'id' => $supplier->id,
                    'company_name' => $supplier->company_name,
                    'website' => $supplier->website,
                    'contact_name' => $mainContact ? $mainContact->name : 'Sin contacto',
                    'email' => $mainContact ? $mainContact->email : '-',
                    'phone' => $mainContact ? $mainContact->phone : '-',
                    'products_count' => $supplier->products()->count(),
                    // Opcional: Mostrar sucursal de origen si es útil
                    'origin_branch_id' => $supplier->branch_id, 
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
            'website' => 'nullable|url|max:255',
            'contacts' => 'required|array|min:1',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if (!$branchId) {
            return back()->with('error', 'No se ha identificado la sucursal activa.');
        }

        DB::transaction(function () use ($validated, $branchId) {
            
            // Creamos el proveedor registrando la sucursal de origen (audit)
            // pero ya no restringiremos su vista por este campo.
            $supplier = Supplier::create([
                'branch_id' => $branchId,
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
            ]);

            foreach ($validated['contacts'] as $contactData) {
                $contactData['branch_id'] = $branchId;
                $supplier->contacts()->create($contactData);
            }
        });

        return redirect()->route('suppliers.index')->with('success', 'Proveedor y contactos registrados exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        // if ($supplier->branch_id !== $branchId) abort(403);

        $supplier->load('contacts');

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
        ]);
    }

    /**
     * API Interna: Obtiene los productos ASIGNADOS a un proveedor.
     */
    public function fetchAssignedProducts(Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        // Cualquiera puede ver los productos asignados a cualquier proveedor

        $assignedProducts = $supplier->products()
            ->with('media')
            ->select('products.id', 'products.name', 'products.sku')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image_url' => $product->getFirstMediaUrl('product_images') ?: $product->image_path,
                    'purchase_price' => $product->pivot->purchase_price,
                    'currency' => $product->pivot->currency,
                    'supplier_sku' => $product->pivot->supplier_sku,
                    'delivery_days' => $product->pivot->delivery_days,
                ];
            });

        return response()->json($assignedProducts);
    }

    /**
     * NUEVO MÉTODO: API Interna para cargar productos disponibles asíncronamente.
     */
    public function fetchAvailableProducts(Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        
        // Obtenemos IDs que ya tiene para excluirlos
        $assignedIds = $supplier->products()->pluck('products.id')->toArray();
        
        // Cargamos el catálogo restante
        // NOTA: Si los productos SÍ son por sucursal, aquí tal vez quieras 
        // filtrar Product::where('branch_id', $currentBranch)... 
        // Si los productos también son globales, déjalo así.
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
        // ELIMINADO: Check de seguridad de sucursal para permitir edición compartida
        // if ($supplier->branch_id !== $branchId) return inertia('Forbidden403');

        $supplier->load('contacts');

        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        // if ($supplier->branch_id !== $branchId) return inertia('Forbidden403');
        
        // Mantenemos el branchId del usuario actual para los nuevos contactos,
        // o podríamos usar $supplier->branch_id para mantener consistencia con el creador original.
        // Usaremos el del usuario actual por trazabilidad de quién creó el contacto.
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            
            'contacts' => 'required|array|min:1',
            'contacts.*.id' => 'nullable|integer',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($supplier, $validated, $branchId) {
            $supplier->update([
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
            ]);

            $inputContacts = collect($validated['contacts']);
            
            $inputIds = $inputContacts->pluck('id')->filter();
            $supplier->contacts()->whereNotIn('id', $inputIds)->delete();

            foreach ($inputContacts as $contactData) {
                // Si es nuevo, asignamos la sucursal del usuario que edita
                if (!isset($contactData['id']) || !$contactData['id']) {
                    $contactData['branch_id'] = $branchId; 
                }
                // (Si ya existe, mantenemos su branch_id original que no viene en el request pero está en BD)

                if (isset($contactData['id']) && $contactData['id']) {
                    $supplier->contacts()->where('id', $contactData['id'])->update($contactData);
                } else {
                    $supplier->contacts()->create($contactData);
                }
            }
        });

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        // Advertencia: Cualquier usuario con permiso de eliminar proveedores podrá eliminar 
        // proveedores de otras sucursales.
        
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'No se puede eliminar porque tiene Órdenes de Compra.');
        }
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado.');
    }

    // -------------------------------------------------------------------------
    // MÉTODOS PARA ASIGNACIÓN DE PRODUCTOS
    // -------------------------------------------------------------------------

    public function assignProduct(Request $request, Supplier $supplier)
    {
        // ELIMINADO: Check de seguridad de sucursal
        // if ($supplier->branch_id !== $branchId) return inertia('Forbidden403');

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
        // ELIMINADO: Check de seguridad de sucursal
        // if ($supplier->branch_id !== $branchId) return inertia('Forbidden403');

        $supplier->products()->detach($product->id);
        
        return back()->with('success', 'Producto removido del proveedor.');
    }
}