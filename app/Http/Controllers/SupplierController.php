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
     * Muestra el listado de proveedores PERTENECIENTES a la sucursal actual.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        $search = $filters['search'] ?? null;
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $suppliers = Supplier::query()
            ->where('branch_id', $branchId)
            ->withCount('contacts') // Contamos contactos reales
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                      // Buscamos también en los contactos relacionados
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
                // Obtenemos el contacto principal para mostrar en la tabla
                $mainContact = $supplier->mainContact; 
                
                return [
                    'id' => $supplier->id,
                    'company_name' => $supplier->company_name,
                    'website' => $supplier->website,
                    // Mostramos datos del contacto principal o un fallback
                    'contact_name' => $mainContact ? $mainContact->name : 'Sin contacto',
                    'email' => $mainContact ? $mainContact->email : '-',
                    'phone' => $mainContact ? $mainContact->phone : '-',
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
        // 1. Validación Principal
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            
            // Validación del Array de Contactos
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

        // Usamos DB Transaction para asegurar integridad (Supplier + Contactos)
        DB::transaction(function () use ($validated, $branchId) {
            
            // 2. Crear Proveedor
            $supplier = Supplier::create([
                'branch_id' => $branchId,
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
                // Dejamos nulos los campos legacy (contact_name, etc)
            ]);

            // 3. Crear Contactos
            foreach ($validated['contacts'] as $contactData) {
                // Inyectamos branch_id al contacto también
                $contactData['branch_id'] = $branchId;
                $supplier->contacts()->create($contactData);
            }
        });

        return redirect()->route('suppliers.index')->with('success', 'Proveedor y contactos registrados exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            abort(403);
        }

        // Cargar contactos para la vista de detalle
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
     * API Interna: Obtiene los productos ASIGNADOS a un proveedor (con precios pactados).
     * Ruta esperada: suppliers.products.assigned
     */
    public function fetchAssignedProducts(Supplier $supplier)
    {
        // Seguridad Tenant
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $assignedProducts = $supplier->products()
            ->with('media')
            // Seleccionamos campos base del producto
            ->select('products.id', 'products.name', 'products.sku')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    // Priorizamos imagen de Spatie, fallback a path simple si existe
                    'image_url' => $product->getFirstMediaUrl('product_images') ?: $product->image_path,
                    
                    // DATOS PIVOTE (Cruciales para la orden de compra)
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
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            return inertia('Forbidden403');
        }

        // IMPORTANTE: Cargar los contactos existentes para que el formulario los muestre
        $supplier->load('contacts');

        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            return inertia('Forbidden403');
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            
            'contacts' => 'required|array|min:1',
            'contacts.*.id' => 'nullable|integer', // ID presente si es edición de uno existente
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($supplier, $validated, $branchId) {
            // 1. Actualizar datos base
            $supplier->update([
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
            ]);

            // 2. Sincronizar Contactos
            // Estrategia: Obtener IDs enviados, eliminar los que no están, actualizar existentes y crear nuevos.
            
            $inputContacts = collect($validated['contacts']);
            
            // A) Eliminar contactos que ya no vienen en el request
            $inputIds = $inputContacts->pluck('id')->filter(); // Solo IDs no nulos
            $supplier->contacts()->whereNotIn('id', $inputIds)->delete();

            // B) Crear o Actualizar
            foreach ($inputContacts as $contactData) {
                $contactData['branch_id'] = $branchId; // Asegurar tenant

                if (isset($contactData['id']) && $contactData['id']) {
                    // Actualizar existente (verificando que pertenezca al supplier)
                    $supplier->contacts()->where('id', $contactData['id'])->update($contactData);
                } else {
                    // Crear nuevo
                    $supplier->contacts()->create($contactData);
                }
            }
        });

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) { return inertia('Forbidden403'); }
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
        // SEGURIDAD
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($supplier->branch_id !== $branchId) {
            return inertia('Forbidden403');
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
            return inertia('Forbidden403');
        }

        $supplier->products()->detach($product->id);
        
        return back()->with('success', 'Producto removido del proveedor.');
    }
}