<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    /**
     * Muestra el listado de clientes con su SALDO PENDIENTE calculado.
     */
    public function index(Request $request)
    {
        // 1. Recibimos el nuevo filtro 'address_filter'
        $filters = $request->only(['search', 'address_filter']);
        $search = $filters['search'] ?? null;
        $addressFilter = $filters['address_filter'] ?? null;
        
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $clients = Client::query()
            ->where('branch_id', $branchId)
            ->with('contacts') // Eager load de contactos
            
            // Filtro General (Nombre, RFC, Contactos)
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%")
                      ->orWhereHas('contacts', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                      });
                });
            })
            
            // 2. NUEVO: Filtro específico por Dirección (Estado, Municipio, Colonia)
            ->when($addressFilter, function (Builder $query, $addressFilter) {
                $query->where(function ($q) use ($addressFilter) {
                    $q->where('state', 'like', "%{$addressFilter}%")
                      ->orWhere('municipality', 'like', "%{$addressFilter}%")
                      ->orWhere('neighborhood', 'like', "%{$addressFilter}%")
                      // Opcional: También buscar en calle si lo deseas
                      ->orWhere('street', 'like', "%{$addressFilter}%");
                });
            })

            ->withSum(['serviceOrders as total_debt' => function ($query) {
                $query->whereNotIn('status', ['Cancelado', 'Cotización']);
            }], 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(function ($client) {
                $debt = $client->total_debt ?? 0;
                $paid = $client->total_paid ?? 0;
                $balance = $debt - $paid;
                
                $mainContact = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'contact_person' => $client->contact_person,
                    'email' => $mainContact ? $mainContact->email : '-',
                    'phone' => $mainContact ? $mainContact->phone : '-',
                    'tax_id' => $client->tax_id,
                    'full_address' => $client->full_address, 
                    'balance' => round($balance, 2),
                    'has_debt' => $balance > 1.00,
                ];
            });

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        // 1. Validaciones
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            
            // Dirección Atomizada
            'road_type' => 'nullable|string|max:50', // NUEVO CAMPO
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:50',
            'interior_number' => 'nullable|string|max:50',
            'neighborhood' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            
            'coordinates' => 'nullable|string',
            'notes' => 'nullable|string',

            // Contactos Polimórficos (Array)
            'contacts' => 'required|array|min:1',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100', // NUEVO CAMPO (Puesto/Parentesco)
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if (!$branchId) {
            return back()->with('error', 'No se ha identificado la sucursal activa.');
        }

        DB::transaction(function () use ($validated, $branchId) {
            // 2. Crear Cliente
            // Extraemos los campos propios del cliente, excluyendo 'contacts'
            $clientData = collect($validated)->except(['contacts'])->toArray();
            $clientData['branch_id'] = $branchId;
            
            $client = Client::create($clientData);

            // 3. Crear Contactos Polimórficos
            foreach ($validated['contacts'] as $contactData) {
                $contactData['branch_id'] = $branchId;
                $client->contacts()->create($contactData);
            }
        });

        // Redirigimos al index o al show, según preferencia. Aquí uso index para ver la lista.
        // O podemos ir al show del ultimo cliente creado si recuperamos el ID fuera de la transacción.
        return redirect()->route('clients.index')
            ->with('success', 'Cliente registrado exitosamente.');
    }

    public function show(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para ver este cliente.');
        }

        $client->load([
            'contacts', 
            'serviceOrders' => function ($q) {
                $q->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'start_date')
                  ->orderBy('created_at', 'desc')
                  ->take(10);
            },
            'payments' => function ($q) {
                $q->with('serviceOrder:id,total_amount,created_at', 'media') 
                  ->orderBy('payment_date', 'desc')
                  ->take(10);
            },
        ]);

        // --- NUEVA LÓGICA ---
        // Transformamos los pagos para inyectar la URL del comprobante
        $client->payments->transform(function ($payment) {
            // Busca en la colección 'payments' o en la 'default'
            $url = $payment->getFirstMediaUrl('payments') ?: $payment->getFirstMediaUrl('default');
            
            // Asignamos una propiedad temporal para fácil acceso en Vue
            $payment->receipt_url = $url ? $url : null;
            return $payment;
        });
        // --------------------

        $totalDebt = $client->serviceOrders()->whereNotIn('status', ['Cancelado', 'Cotización'])->sum('total_amount');
        $totalPaid = $client->payments()->sum('amount');
        $balance = $totalDebt - $totalPaid;

        $documents = $client->getMedia('documents')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'category' => $media->getCustomProperty('category', 'General'),
                'created_at' => $media->created_at->toISOString(),
                'url' => $media->getUrl(), 
                'size' => $media->human_readable_size,
            ];
        });

        return Inertia::render('Clients/Show', [
            'client' => array_merge($client->toArray(), ['documents' => $documents]),
            'stats' => [
                'total_debt' => $totalDebt,
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'services_count' => $client->serviceOrders()->count(),
            ]
        ]);
    }

    public function edit(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        $client->load('contacts'); // Cargar contactos para edición

        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        // Mismas reglas que store, pero 'contacts.*.id' puede venir para actualizar existentes
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            
            'road_type' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:50',
            'interior_number' => 'nullable|string|max:50',
            'neighborhood' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            
            'coordinates' => 'nullable|string',
            'notes' => 'nullable|string',

            // Contactos
            'contacts' => 'required|array|min:1',
            'contacts.*.id' => 'nullable|integer',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.job_title' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($client, $validated, $branchId) {
            // Actualizar datos del cliente
            $clientData = collect($validated)->except(['contacts'])->toArray();
            $client->update($clientData);

            // Sincronizar Contactos (Lógica idéntica a Supplier)
            $inputContacts = collect($validated['contacts']);
            $inputIds = $inputContacts->pluck('id')->filter();
            
            // Eliminar los que ya no vienen en el array
            $client->contacts()->whereNotIn('id', $inputIds)->delete();

            foreach ($inputContacts as $contactData) {
                if (!isset($contactData['id']) || !$contactData['id']) {
                    // Nuevo contacto
                    $contactData['branch_id'] = $branchId;
                    $client->contacts()->create($contactData);
                } else {
                    // Actualizar existente
                    $client->contacts()->where('id', $contactData['id'])->update($contactData);
                }
            }
        });

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Información del cliente actualizada.');
    }

    public function destroy(Client $client)
    {
        // Validar sucursal
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            return inertia('Forbidden403');
        }

        // En lugar de usar back()->with('error'), lanzamos excepciones de validación.
        // Esto hace que Inertia ejecute el callback 'onError' en el frontend.
        
        if ($client->serviceOrders()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar: El cliente tiene historial de órdenes de servicio.'
            ]);
        }
        
        if ($client->payments()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar: El cliente tiene pagos registrados.'
            ]);
        }

        // Eliminar contactos relacionados (Polimórficos)
        $client->contacts()->delete(); 

        // Eliminar cliente
        $client->delete();

        // Si llega aquí, es un éxito real y redirigimos
        return redirect()->route('clients.index')->with('success', 'Cliente eliminado correctamente.');
    }

    /**
     * Sube uno o múltiples documentos al expediente del cliente.
     */
    public function uploadDocument(Request $request, Client $client)
    {
        // Validación de seguridad (Branch)
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        // Validación actualizada para múltiples archivos
        $request->validate([
            'files' => 'required|array', // Ahora esperamos un array
            'files.*' => 'file|max:10240', // Cada archivo individualmente (10MB max)
            'category' => 'nullable|string|max:50',
        ]);

        // Procesar la subida múltiple
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $client->addMedia($file) // Usamos addMedia directamente con el objeto archivo
                    ->withCustomProperties(['category' => $request->input('category', 'General')])
                    ->toMediaCollection('documents');
            }
        }

        return back()->with('success', 'Documentos subidos correctamente.');
    }

    public function getClientDetails(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) abort(403);

        return response()->json([
            'road_type' => $client->road_type,
            'street' => $client->street,
            'exterior_number' => $client->exterior_number,
            'interior_number' => $client->interior_number,
            'neighborhood' => $client->neighborhood,
            'municipality' => $client->municipality,
            'state' => $client->state,
            'zip_code' => $client->zip_code,
            'country' => $client->country,
        ]);
    }
}