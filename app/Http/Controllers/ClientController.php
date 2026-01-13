<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class ClientController extends Controller
{
    /**
     * Muestra el listado de clientes con su SALDO PENDIENTE calculado.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $search = $filters['search'] ?? null;
        
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $clients = Client::query()
            ->where('branch_id', $branchId)
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('email_secondary', 'like', "%{$search}%") // Busqueda en correo secundario
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%"); 
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

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'contact_person' => $client->contact_person,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'tax_id' => $client->tax_id,
                    // Devolvemos la dirección completa concatenada (usando el accessor del modelo)
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            
            // Contacto
            'email' => 'nullable|email|max:255',
            'email_secondary' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            
            // Dirección Atomizada (Todo nullable)
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
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if (!$branchId) {
            return back()->with('error', 'No se ha identificado la sucursal activa.');
        }

        $validated['branch_id'] = $branchId;

        $client = Client::create($validated);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Cliente registrado exitosamente.');
    }

    public function show(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para ver este cliente.');
        }

        $client->load([
            'serviceOrders' => function ($q) {
                $q->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'start_date')
                  ->orderBy('created_at', 'desc')
                  ->take(10);
            },
            'payments' => function ($q) {
                $q->with('serviceOrder:id,total_amount,created_at')
                  ->orderBy('payment_date', 'desc')
                  ->take(10);
            },
        ]);

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

        // Nota: Al usar ->toArray(), todos los nuevos campos (street, etc) se incluyen automáticamente.
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

    public function uploadDocument(Request $request, Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        $request->validate([
            'file' => 'required|file|max:10240', 
            'category' => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('file')) {
            $client->addMediaFromRequest('file')
                ->withCustomProperties(['category' => $request->input('category', 'General')])
                ->toMediaCollection('documents');
        }

        return back()->with('success', 'Documento subido correctamente.');
    }

    public function edit(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        // Mismas reglas de validación que el Store
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            
            'email' => 'nullable|email|max:255',
            'email_secondary' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            
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
        ]);

        $client->update($validated);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Información del cliente actualizada.');
    }

    public function destroy(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) return inertia('Forbidden403');

        if ($client->serviceOrders()->exists()) {
            return back()->with('error', 'No se puede eliminar: El cliente tiene historial de servicios.');
        }
        
        if ($client->payments()->exists()) {
            return back()->with('error', 'No se puede eliminar: El cliente tiene pagos registrados.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }

    
    /**
     * API: Obtener detalles del cliente para autocompletado (AJAX)
     */
    public function getClientDetails(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        // Seguridad: No devolver datos si es de otra sucursal
        if ($client->branch_id !== $branchId) {
            abort(403);
        }

        return response()->json([
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