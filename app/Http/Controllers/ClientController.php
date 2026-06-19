<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use App\Models\TechnicalVisit;
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
                    'type' => $client->type,
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
            'type' => 'required|in:Prospecto,Cliente',
            'lead_source' => 'nullable|string|max:255',
            
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
                $q->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'start_date', 
                           'payment_method', 'down_payment', 'price_per_module', 'system_type',
                           'service_number')
                  ->withSum('payments as total_paid', 'amount')
                  ->orderBy('created_at', 'desc');
            },
            'payments' => function ($q) {
                $q->with(['serviceOrder:id,total_amount,created_at,payment_method', 'media']) 
                  ->orderBy('payment_date', 'desc')
                  ->take(15);
            },
            'tickets' => function ($q) {
                $q->select('id', 'client_id', 'title', 'status', 'priority', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->take(15);
            },
        ]);

        // Transformar service orders para agregar campos calculados
        $client->serviceOrders->transform(function ($order) {
            $paid = (float) ($order->total_paid ?? 0);
            $total = (float) ($order->total_amount ?? 0);
            $order->amount_paid = $paid;
            $order->remaining = max(0, $total - $paid);
            
            // Calcular fecha límite de liquidación según plan de pago
            $monthsMap = [
                'Contado' => 0, '3 MSI' => 3, '6 MSI' => 6,
                '9 MSI' => 9, '12 MSI' => 12,
            ];
            $months = $monthsMap[$order->payment_method] ?? null;
            
            if ($months !== null && $months > 0) {
                $order->liquidation_deadline = $order->created_at->copy()->addMonths($months)->format('Y-m-d');
                $order->is_overdue = now()->startOfDay()->gt($order->created_at->copy()->addMonths($months)->startOfDay());
            } else {
                $order->liquidation_deadline = null;
                $order->is_overdue = false;
            }

            // Calcular estatus global de pagos de la orden
            $order->payment_status_summary = $this->calculateOrderPaymentSummary($order);
            
            return $order;
        });

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
            'type' => 'required|in:Prospecto,Cliente',
            'lead_source' => 'nullable|string|max:255',
            
            // Dirección Atomizada
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

        // Desvincular visitas técnicas relacionadas (evitar cascadeOnDelete)
        TechnicalVisit::where('client_id', $client->id)->update(['client_id' => null]);

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

    /**
     * API JSON: Reporte de cartera de deuda (paginado).
     */
    public function apiDebtReport(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        $page = (int) $request->input('page', 1);
        $perPage = 30;
        $search = $request->input('search');
        $paymentMethod = $request->input('payment_method');
        $delinquency = $request->input('delinquency'); // 'late' o 'defaulted'

        $allClients = Client::where('branch_id', $branchId)
            ->whereHas('serviceOrders', fn($q) => $q->whereNotIn('status', ['Cancelado', 'Cotización']))
            ->with(['contacts', 'serviceOrders' => fn($q) => $q->whereNotIn('status', ['Cancelado', 'Cotización'])
                ->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'payment_method', 'down_payment')
                ->withSum('payments as total_paid', 'amount')
                ->with('paymentInstallments')
                ->orderBy('created_at', 'desc')])
            ->orderBy('name')->get();

        // Inicializar proyección mensual (12 meses desde ahora)
        $globalMonthlyProjection = [];
        for ($m = 0; $m < 12; $m++) {
            $date = now()->startOfMonth()->addMonthsNoOverflow($m);
            $globalMonthlyProjection[] = [
                'month' => $date->month,
                'year' => $date->year,
                'label' => $date->locale('es')->isoFormat('MMMM YYYY'),
                'total' => 0,
            ];
        }

        $allData = $allClients->map(function ($client) use (&$globalMonthlyProjection) {
            $totalDebt = 0; $totalPaid = 0; $orders = [];
            foreach ($client->serviceOrders as $order) {
                $paid = (float) ($order->total_paid ?? 0);
                $total = (float) ($order->total_amount ?? 0);
                $remaining = max(0, $total - $paid);
                if ($remaining <= 1) continue;
                $totalDebt += $total; $totalPaid += $paid;
                $monthsMap = ['Contado' => 0, '3 MSI' => 3, '6 MSI' => 6, '9 MSI' => 9, '12 MSI' => 12];
                $months = $monthsMap[$order->payment_method] ?? null;
                $deadline = null; $isOverdue = false; $daysSinceProj = null;
                if ($months !== null && $months > 0) {
                    $deadlineDate = $order->created_at->copy()->addMonths($months);
                    $deadline = $deadlineDate->format('Y-m-d');
                    $isOverdue = now()->startOfDay()->gt($deadlineDate->startOfDay());
                    $daysSinceProj = (int) $deadlineDate->startOfDay()->diffInDays(now()->startOfDay(), false);
                }

                // --- Calcular cuotas desde payment_installments ---
                $installments = [];
                $dbInstallments = $order->paymentInstallments->sortBy('installment_number');
                if ($dbInstallments->isNotEmpty()) {
                    foreach ($dbInstallments as $inst) {
                        $projDate = $inst->projected_date instanceof \Carbon\Carbon
                            ? $inst->projected_date
                            : \Carbon\Carbon::parse($inst->projected_date);
                        $isPaid = $inst->status === 'paid' || $inst->status === 'on_time' || (bool) $inst->payment_id;
                        $installments[] = [
                            'projected_date' => $projDate->format('Y-m-d'),
                            'amount' => round((float) $inst->amount, 2),
                            'is_paid' => $isPaid,
                        ];
                    }
                } else {
                    // Fallback legacy
                    if ($months !== null && $months > 0) {
                        $installmentAmount = $remaining / $months;
                        $startDate = $order->created_at;
                        for ($i = 1; $i <= $months; $i++) {
                            $installments[] = [
                                'projected_date' => $startDate->copy()->addMonths($i)->format('Y-m-d'),
                                'amount' => round($installmentAmount, 2),
                                'is_paid' => false,
                            ];
                        }
                    } elseif ($months === 0) {
                        $installments[] = [
                            'projected_date' => $order->created_at->format('Y-m-d'),
                            'amount' => $remaining,
                            'is_paid' => $remaining <= 0,
                        ];
                    }
                }

                // Acumular en proyección mensual global
                foreach ($installments as $inst) {
                    if ($inst['is_paid']) continue;
                    $instDate = \Carbon\Carbon::parse($inst['projected_date']);
                    foreach ($globalMonthlyProjection as &$mp) {
                        if ($mp['month'] === (int) $instDate->month && $mp['year'] === (int) $instDate->year) {
                            $mp['total'] += $inst['amount'];
                            break;
                        }
                    }
                }

                $orders[] = ['id' => $order->id, 'status' => $order->status, 'total_amount' => $total,
                    'paid' => $paid, 'remaining' => $remaining, 'payment_method' => $order->payment_method,
                    'liquidation_deadline' => $deadline, 'is_overdue' => $isOverdue,
                    'days_since_projected' => $daysSinceProj, 'created_at' => $order->created_at->format('Y-m-d')];
            }
            if (empty($orders)) return null;
            return ['id' => $client->id, 'name' => $client->name, 'tax_id' => $client->tax_id,
                'phone' => $client->contacts->first()?->phone, 'total_debt' => $totalDebt,
                'total_paid' => $totalPaid, 'balance' => $totalDebt - $totalPaid, 'orders' => $orders];
        })->filter()->values();

        // Aplicar filtros server-side
        if ($search) {
            $allData = $allData->filter(fn($c) =>
                stripos($c['name'], $search) !== false
                || ($c['tax_id'] && stripos($c['tax_id'], $search) !== false)
            )->values();
        }
        if ($paymentMethod) {
            $allData = $allData->filter(fn($c) =>
                $c['orders'] && collect($c['orders'])->contains('payment_method', $paymentMethod)
            )->values();
        }
        if ($delinquency === 'late') {
            $allData = $allData->filter(fn($c) =>
                $c['orders'] && collect($c['orders'])->contains(fn($o) =>
                    $o['days_since_projected'] !== null && $o['days_since_projected'] >= 6 && $o['days_since_projected'] <= 10
                )
            )->values();
        } elseif ($delinquency === 'defaulted') {
            $allData = $allData->filter(fn($c) =>
                $c['orders'] && collect($c['orders'])->contains(fn($o) =>
                    $o['days_since_projected'] !== null && $o['days_since_projected'] >= 11
                )
            )->values();
        }

        $total = $allData->count();
        $paginated = $allData->forPage($page, $perPage)->values();

        // Redondear y calcular totales de proyección
        $grandTotalProjected = 0;
        $grandTotalReceived = $allData->sum('total_paid');
        foreach ($globalMonthlyProjection as &$mp) {
            $mp['total'] = round($mp['total'], 2);
            $grandTotalProjected += $mp['total'];
        }
        $grandTotalProjected = round($grandTotalProjected, 2);
        $grandTotalReceived = round($grandTotalReceived, 2);

        return response()->json([
            'reportData' => $paginated,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'monthlyProjection' => $globalMonthlyProjection,
            'grandTotalProjected' => $grandTotalProjected,
            'grandTotalReceived' => $grandTotalReceived,
        ]);
    }

    /**
     * Reporte de Cartera de Deuda - Vista imprimible.
     */
    public function debtReport()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $clients = Client::where('branch_id', $branchId)
            ->whereHas('serviceOrders', function ($q) {
                $q->whereNotIn('status', ['Cancelado', 'Cotización']);
            })
            ->with(['serviceOrders' => function ($q) {
                $q->whereNotIn('status', ['Cancelado', 'Cotización'])
                  ->select('id', 'client_id', 'status', 'total_amount', 'created_at', 'payment_method', 'down_payment')
                  ->withSum('payments as total_paid', 'amount')
                  ->with('paymentInstallments')
                  ->orderBy('created_at', 'desc');
            }])
            ->orderBy('name')
            ->get();

        // Inicializar acumulador global de proyección mensual (12 meses)
        $globalMonthlyProjection = [];
        for ($m = 0; $m < 12; $m++) {
            $date = now()->startOfMonth()->addMonthsNoOverflow($m);
            $globalMonthlyProjection[] = [
                'month' => $date->month,
                'year' => $date->year,
                'label' => $date->locale('es')->isoFormat('MMMM YYYY'),
                'total' => 0,
            ];
        }

        // Totales globales para la sección de proyección
        $grandTotalProjected = 0;
        $grandTotalReceived = 0;

        // Filtrar solo clientes con saldo pendiente y calcular datos
        $reportData = $clients->map(function ($client) use (&$globalMonthlyProjection, &$grandTotalProjected, &$grandTotalReceived) {
            $totalDebt = 0;
            $totalPaid = 0;
            $orders = [];

            foreach ($client->serviceOrders as $order) {
                $paid = (float) ($order->total_paid ?? 0);
                $total = (float) ($order->total_amount ?? 0);
                $remaining = max(0, $total - $paid);

                if ($remaining <= 0) continue; // Solo órdenes con saldo

                $totalDebt += $total;
                $totalPaid += $paid;

                $monthsMap = [
                    'Contado' => 0, '3 MSI' => 3, '6 MSI' => 6,
                    '9 MSI' => 9, '12 MSI' => 12,
                ];
                $months = $monthsMap[$order->payment_method] ?? null;
                $deadline = null;
                $isOverdue = false;

                if ($months !== null && $months > 0) {
                    $deadlineDate = $order->created_at->copy()->addMonths($months);
                    $deadline = $deadlineDate->format('Y-m-d');
                    $isOverdue = now()->startOfDay()->gt($deadlineDate->startOfDay());
                }

                // --- Leer cuotas desde payment_installments (fuente de verdad) ---
                $dbInstallments = $order->paymentInstallments->sortBy('installment_number');
                $installments = [];

                if ($dbInstallments->isNotEmpty()) {
                    // Usar registros reales de la BD
                    foreach ($dbInstallments as $inst) {
                        $projDate = $inst->projected_date instanceof \Carbon\Carbon
                            ? $inst->projected_date
                            : \Carbon\Carbon::parse($inst->projected_date);
                        $daysSince = (int) $projDate->startOfDay()->diffInDays(now()->startOfDay(), false);
                        $isPaid = $inst->status === 'paid' || $inst->status === 'on_time' || (bool) $inst->payment_id;

                        $installments[] = [
                            'installment' => $inst->installment_number,
                            'projected_date' => $projDate->format('Y-m-d'),
                            'amount' => round((float) $inst->amount, 2),
                            'is_paid' => $isPaid,
                            'is_past' => $daysSince > 0 && !$isPaid,
                        ];
                    }
                } else {
                    // Fallback: calcular cuotas sobre la marcha (órdenes legacy sin payment_installments)
                    if ($months !== null && $months > 0) {
                        $installmentCount = $months;
                        $installmentAmount = $remaining / $installmentCount;
                        $startDate = $order->created_at;

                        for ($i = 1; $i <= $installmentCount; $i++) {
                            $projDate = $startDate->copy()->addMonths($i);
                            $daysSince = (int) $projDate->startOfDay()->diffInDays(now()->startOfDay(), false);

                            $installments[] = [
                                'installment' => $i,
                                'projected_date' => $projDate->format('Y-m-d'),
                                'amount' => round($installmentAmount, 2),
                                'is_paid' => false,
                                'is_past' => $daysSince > 0,
                            ];
                        }
                    } elseif ($months === 0) {
                        $installments[] = [
                            'installment' => 1,
                            'projected_date' => $order->created_at->format('Y-m-d'),
                            'amount' => $remaining,
                            'is_paid' => $remaining <= 0,
                            'is_past' => true,
                        ];
                    }
                }

                // --- Acumular en la proyección mensual global ---
                foreach ($installments as $inst) {
                    if ($inst['is_paid']) continue; // No contar pagadas en proyección futura
                    $instDate = \Carbon\Carbon::parse($inst['projected_date']);
                    foreach ($globalMonthlyProjection as &$mp) {
                        if ($mp['month'] === (int) $instDate->month && $mp['year'] === (int) $instDate->year) {
                            $mp['total'] += $inst['amount'];
                            break;
                        }
                    }
                }

                $orders[] = [
                    'id' => $order->id,
                    'status' => $order->status,
                    'total_amount' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'payment_method' => $order->payment_method,
                    'liquidation_deadline' => $deadline,
                    'is_overdue' => $isOverdue,
                    'created_at' => $order->created_at->format('Y-m-d'),
                    'installments' => $installments,
                ];
            }

            if (empty($orders)) return null;

            return [
                'id' => $client->id,
                'name' => $client->name,
                'tax_id' => $client->tax_id,
                'phone' => $client->contact_person ?? ($client->contacts->first()?->phone ?? null),
                'total_debt' => $totalDebt,
                'total_paid' => $totalPaid,
                'balance' => $totalDebt - $totalPaid,
                'orders' => $orders,
            ];
        })->filter()->values();

        // Calcular totales globales para la proyección
        $grandTotalProjected = collect($globalMonthlyProjection)->sum('total');
        $grandTotalReceived = $reportData->sum('total_paid');

        // Redondear totales de proyección mensual
        foreach ($globalMonthlyProjection as &$mp) {
            $mp['total'] = round($mp['total'], 2);
        }

        return Inertia::render('Clients/DebtReport', [
            'reportData' => $reportData,
            'monthlyProjection' => $globalMonthlyProjection,
            'grandTotalProjected' => round($grandTotalProjected, 2),
            'grandTotalReceived' => round($grandTotalReceived, 2),
            'generatedAt' => now()->locale('es')->isoFormat('D [de] MMMM [del] YYYY, h:mm a'),
        ]);
    }

    /**
     * Calcula un resumen del estado de pagos de una orden.
     * Retorna un conteo de cuotas por estatus: on_time, late, defaulted, pending, upcoming.
     */
    private function calculateOrderPaymentSummary($order): array
    {
        $method = $order->payment_method;
        if (!$method || $method === 'Personalizado' || in_array($order->status, ['Cotización', 'Cancelado'])) {
            return ['has_schedule' => false];
        }

        $totalAmount = (float) $order->total_amount;
        $downPayment = (float) ($order->down_payment ?? 0);

        // Sumar pagos registrados con concepto "Anticipo" al total del anticipo
        $order->loadMissing('payments');
        $anticipoTotal = (float) $order->payments->where('notes', 'Anticipo')->sum('amount');
        $totalDownPayment = $downPayment + $anticipoTotal;

        $startDate = $order->created_at ?? now();

        $monthsMap = [
            'Contado' => 0, '3 MSI' => 3, '6 MSI' => 6,
            '9 MSI' => 9, '12 MSI' => 12,
        ];
        $months = $monthsMap[$method] ?? null;
        if ($months === null) return ['has_schedule' => false];

        $installmentCount = $months === 0 ? 1 : $months;
        $remainingAmount = $totalAmount - $totalDownPayment;
        $installmentAmount = $remainingAmount / max(1, $installmentCount);

        $summary = ['on_time' => 0, 'late' => 0, 'defaulted' => 0, 'pending' => 0, 'upcoming' => 0];
        $earliestLateDate = null;

        // IDs de pagos de anticipo para excluir del matching
        $anticipoPaymentIds = $order->payments->where('notes', 'Anticipo')->pluck('id')->toArray();

        for ($i = 1; $i <= $installmentCount; $i++) {
            $projDate = $startDate->copy()->addMonths($i === 1 && $months === 0 ? 0 : $i);
            // $projDate->diffInDays(now(), false): NEGATIVO = futuro, POSITIVO = pasado
            $daysSince = (int) $projDate->startOfDay()->diffInDays(now()->startOfDay(), false);

            // Verificar si existe un pago real (NO anticipo) cercano a esta fecha (ventana 15 días antes)
            $hasPayment = $order->payments->contains(function ($p) use ($projDate, $anticipoPaymentIds) {
                if (in_array($p->id, $anticipoPaymentIds)) return false;
                $payDate = \Carbon\Carbon::parse($p->payment_date);
                return $payDate->gte($projDate->copy()->subDays(15));
            });

            if ($hasPayment) {
                $summary['on_time']++;
            } elseif ($daysSince < 0) {
                // Fecha futura: próximo
                $summary['upcoming']++;
            } elseif ($daysSince <= 5) {
                // 0-5 días de atraso: pendiente
                $summary['pending']++;
            } elseif ($daysSince <= 10) {
                // 6-10 días de atraso
                $summary['late']++;
                if ($earliestLateDate === null || $projDate->lt($earliestLateDate)) {
                    $earliestLateDate = $projDate;
                }
            } else {
                // 11+ días de atraso
                $summary['defaulted']++;
                if ($earliestLateDate === null || $projDate->lt($earliestLateDate)) {
                    $earliestLateDate = $projDate;
                }
            }
        }

        return [
            'has_schedule' => true,
            'method' => $method,
            'total_installments' => $installmentCount,
            'installment_amount' => round($installmentAmount, 2),
            'summary' => $summary,
            'show_reminder_button' => $summary['late'] > 0 || $summary['defaulted'] > 0,
            'earliest_late_date' => $earliestLateDate ? $earliestLateDate->format('Y-m-d') : null,
        ];
    }
}