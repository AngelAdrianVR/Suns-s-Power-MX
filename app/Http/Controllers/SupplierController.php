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
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        $search = $filters['search'] ?? null;
        
        $suppliers = Supplier::query()
            ->withCount('contacts') 
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                      ->orWhere('rfc', 'like', "%{$search}%")
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
                    'rfc' => $supplier->rfc,
                    'bank_name' => $supplier->bank_name,
                    'website' => $supplier->website,
                    'contact_name' => $mainContact ? $mainContact->name : 'Sin contacto',
                    'email' => $mainContact ? $mainContact->email : '-',
                    'phone' => $mainContact ? $mainContact->phone : '-',
                    'products_count' => $supplier->products()->count(),
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
            
            // Nuevas validaciones
            'rfc' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'clabe' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20',
            
            // Validación de documentos (array de archivos)
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240', // Max 10MB

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

        DB::transaction(function () use ($validated, $branchId, $request) {
            
            $supplier = Supplier::create([
                'branch_id' => $branchId,
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank_account_holder' => $validated['bank_account_holder'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
            ]);

            // Lógica Spatie Media Library
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $supplier->addMedia($file)
                             ->toMediaCollection('supplier_documents');
                }
            }

            foreach ($validated['contacts'] as $contactData) {
                $contactData['branch_id'] = $branchId;
                $supplier->contacts()->create($contactData);
            }
        });

        return redirect()->route('suppliers.index')->with('success', 'Proveedor registrado exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('contacts');

        // Transformamos los medios de Spatie para el frontend
        $documents = $supplier->getMedia('supplier_documents')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(), // URL pública
            ];
        });

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
            'documents' => $documents,
            'assigned_products' => $assignedProducts,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'rfc' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'clabe' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20',
            
            // Nuevos archivos a agregar
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240',

            'contacts' => 'required|array|min:1',
            'contacts.*.id' => 'nullable|integer',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($supplier, $validated, $branchId, $request) {
            $supplier->update([
                'company_name' => $validated['company_name'],
                'website' => $validated['website'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank_account_holder' => $validated['bank_account_holder'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
            ]);

            // Agregar NUEVOS archivos a la colección existente
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $supplier->addMedia($file)
                             ->toMediaCollection('supplier_documents');
                }
            }

            $inputContacts = collect($validated['contacts']);
            $inputIds = $inputContacts->pluck('id')->filter();
            
            // Eliminar contactos que ya no vienen en el request
            $supplier->contacts()->whereNotIn('id', $inputIds)->delete();

            foreach ($inputContacts as $contactData) {
                if (!isset($contactData['id']) || !$contactData['id']) {
                    $contactData['branch_id'] = $branchId; 
                }

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
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'No se puede eliminar porque tiene Órdenes de Compra.');
        }
        
        // Spatie borra automáticamente los archivos relacionados al eliminar el modelo.
        $supplier->delete();
        
        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado.');
    }

    // ... Resto de métodos (edit, assignProduct, fetch...) se mantienen igual
    
    public function fetchAssignedProducts(Supplier $supplier)
    {
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
    
    public function fetchAvailableProducts(Supplier $supplier)
    {
        $assignedIds = $supplier->products()->pluck('products.id')->toArray();
        
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
        $supplier->load('contacts');
        // Para editar, opcionalmente podrías querer mostrar los docs existentes
        // $supplier->documents = $supplier->getMedia('supplier_documents');
        
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier
        ]);
    }

    public function assignProduct(Request $request, Supplier $supplier)
    {
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
        $supplier->products()->detach($product->id);
        return back()->with('success', 'Producto removido del proveedor.');
    }
}