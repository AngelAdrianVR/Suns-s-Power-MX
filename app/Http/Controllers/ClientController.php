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
        
        // Contexto Multi-tenant
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $clients = Client::query()
            ->where('branch_id', $branchId)
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%"); // Búsqueda por RFC
                });
            })
            // Subconsultas para calcular saldo sin hidratar todos los modelos (Optimización SQL)
            ->withSum(['serviceOrders as total_debt' => function ($query) {
                // Solo sumamos órdenes que generan deuda (ej. no canceladas)
                $query->whereNotIn('status', ['Cancelado', 'Cotización']);
            }], 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(function ($client) {
                // Cálculo de saldo en memoria de resultados paginados
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
                    'balance' => round($balance, 2), // Saldo Pendiente
                    'has_debt' => $balance > 1.00, // Bandera para UI (tolerancia de $1)
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
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:20', // RFC
            'address' => 'nullable|string',
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

    /**
     * Muestra el EXPEDIENTE DEL CLIENTE: Historial, Deuda y Datos.
     */
    public function show(Client $client)
    {
        // Seguridad Tenant
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para ver este cliente.');
        }

        // Cargar relaciones para el expediente
        $client->load([
            // Historial de servicios (últimos 10)
            'serviceOrders' => function ($q) {
                $q->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'start_date')
                  ->orderBy('created_at', 'desc')
                  ->take(10);
            },
            // Historial de pagos recientes (AGREGAMOS with('serviceOrder'))
            'payments' => function ($q) {
                $q->with('serviceOrder:id,total_amount,created_at') // <--- CARGAMOS LA RELACIÓN AQUÍ
                  ->orderBy('payment_date', 'desc')
                  ->take(10);
            },
            // Documentos asociados (usando la relación polimórfica del esquema)
            'documents'
        ]);

        // Calcular estado de cuenta global
        $totalDebt = $client->serviceOrders()->whereNotIn('status', ['Cancelado', 'Cotización'])->sum('total_amount');
        $totalPaid = $client->payments()->sum('amount');
        $balance = $totalDebt - $totalPaid;

        return Inertia::render('Clients/Show', [
            'client' => $client,
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
        if ($client->branch_id !== $branchId) abort(403);

        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) abort(403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:20',
            'address' => 'nullable|string',
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
        if ($client->branch_id !== $branchId) abort(403);

        // Validaciones de integridad antes de borrar
        if ($client->serviceOrders()->exists()) {
            return back()->with('error', 'No se puede eliminar: El cliente tiene historial de servicios.');
        }
        
        if ($client->payments()->exists()) {
            return back()->with('error', 'No se puede eliminar: El cliente tiene pagos registrados.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }
}