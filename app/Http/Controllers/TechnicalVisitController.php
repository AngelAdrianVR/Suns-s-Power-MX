<?php

namespace App\Http\Controllers;

use App\Models\TechnicalVisit;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TechnicalVisitController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;
        
        $filters = $request->only(['search', 'status', 'municipality', 'state', 'date_range', 'system_type']);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $municipality = $filters['municipality'] ?? null;
        $state = $filters['state'] ?? null;
        $systemType = $filters['system_type'] ?? null;
        $dateRange = $filters['date_range'] ?? null;
        $showCompleted = $request->boolean('show_completed', false);

        // Extraer opciones disponibles para los filtros
        $availableMunicipalities = TechnicalVisit::where('branch_id', $branchId)
            ->whereNotNull('municipality')
            ->where('municipality', '!=', '')
            ->distinct()
            ->orderBy('municipality')
            ->pluck('municipality');

        $availableStates = TechnicalVisit::where('branch_id', $branchId)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        $query = TechnicalVisit::query()
            ->with([
                'client:id,name',
                'salesRep:id,name,profile_photo_path',
                'technician:id,name,profile_photo_path'
            ])
            ->where('branch_id', $branchId);

        $visits = $query
            ->when($search, function (Builder $query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere('business_name', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('paternal_surname', 'like', "%{$search}%")
                      ->orWhere('street', 'like', "%{$search}%")
                      ->orWhere('municipality', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            // Por defecto ocultar visitas terminadas, a menos que se pida explícitamente
            ->when(!$showCompleted, function ($query) {
                $query->where('status', '!=', 'Terminada');
            })
            ->when($municipality, function ($query, $municipality) {
                $query->where('municipality', $municipality);
            })
            ->when($state, function ($query, $state) {
                $query->where('state', $state);
            })
            ->when($systemType, function ($query, $systemType) {
                $query->where('system_of_interest', $systemType);
            })
            ->when($dateRange, function ($query, $dateRange) {
                if (is_array($dateRange) && count($dateRange) === 2) {
                    try {
                        $start = is_numeric($dateRange[0]) 
                            ? Carbon::createFromTimestampMs($dateRange[0])->startOfDay()
                            : Carbon::parse($dateRange[0])->startOfDay();
                            
                        $end = is_numeric($dateRange[1])
                            ? Carbon::createFromTimestampMs($dateRange[1])->endOfDay()
                            : Carbon::parse($dateRange[1])->endOfDay();

                        $query->whereBetween('scheduled_at', [$start, $end]);
                    } catch (\Exception $e) {
                        // Ignorar filtro si falla el parseo
                    }
                }
            })
            ->orderBy('scheduled_at', 'asc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($visit) {
                return [
                    'id' => $visit->id,
                    'status' => $visit->status,
                    'prospect_name' => $visit->full_name,
                    'client' => $visit->client ? ['id' => $visit->client->id, 'name' => $visit->client->name] : null,
                    'has_client' => !is_null($visit->client_id),
                    'has_service_order' => !is_null($visit->service_order_id),
                    'address' => $visit->full_address,
                    'municipality' => $visit->municipality,
                    'state' => $visit->state,
                    'system_of_interest' => $visit->system_of_interest,
                    'scheduled_at' => $visit->scheduled_at?->format('d/m/Y H:i'),
                    'requires_long_ladder' => $visit->requires_long_ladder,
                    'google_maps_link' => $visit->google_maps_link,
                    'reschedule_reason' => $visit->reschedule_reason,
                    'rejection_reason' => $visit->rejection_reason,
                    'sales_rep' => $visit->salesRep ? [
                        'name' => $visit->salesRep->name,
                        'photo' => $visit->salesRep->profile_photo_url,
                    ] : null,
                    'created_at_human' => $visit->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('TechnicalVisits/Index', [
            'visits' => $visits,
            'filters' => $filters,
            'statuses' => ['Pendiente', 'Reprogramada', 'Aceptada', 'Terminada', 'Rechazada'],
            'system_types' => ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'],
            'municipalities' => $availableMunicipalities,
            'states' => $availableStates,
        ]);
    }

    public function create()
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        return Inertia::render('TechnicalVisits/Create', [
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => User::role('Ventas')
                ->where('id', '!=', 1)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'business_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'paternal_surname' => 'nullable|string|max:255',
            'maternal_surname' => 'nullable|string|max:255',
            
            'sales_rep_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date',
            
            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'property_use' => 'nullable|in:Residencial,Comercial,Industrial',
            'requires_long_ladder' => 'boolean',
            'property_floors' => 'nullable|integer',
            'number_of_wires' => 'nullable|integer',
            'voltage' => 'nullable|numeric',
            'google_maps_link' => 'nullable|url',
            
            // Dirección
            'road_type' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:50',
            'interior_number' => 'nullable|string|max:50',
            'neighborhood' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            
            'internal_notes' => 'nullable|string',
            
            // Sistema de interés
            'system_of_interest' => 'nullable|in:Interconectado,Autónomo,Back-up,Bombeo',
            'module_quantity' => 'nullable|integer',
            'module_brand' => 'nullable|string|max:255',
            'module_capacity' => 'nullable|numeric',
            'budget' => 'nullable|numeric',
            
            // Cálculos
            'gross_installed_capacity' => 'nullable|numeric',
            'estimated_daily_generation' => 'nullable|numeric',
            'estimated_monthly_generation' => 'nullable|numeric',
            'estimated_monthly_saving' => 'nullable|numeric',
            
            // Baterías
            'battery_quantity' => 'nullable|integer',
            'battery_brand' => 'nullable|string|max:255',
            'battery_capacity' => 'nullable|numeric',
            'backup_devices' => 'nullable|array', // JSON
            
            // Archivos y Extras del formulario
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240',
            'phone' => 'nullable|string',
            'lead_source' => 'nullable|string',
        ]);

        $validated['branch_id'] = $branchId;
        $validated['status'] = 'Pendiente';

        DB::transaction(function () use ($validated, $request) {
            $visit = TechnicalVisit::create(collect($validated)->except(['documents'])->toArray());
            
            // Subir archivos si existen
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $visit->addMedia($file)->toMediaCollection('documents');
                }
            }
        });

        return redirect()->route('technical-visits.index')
            ->with('success', 'Visita técnica programada exitosamente.');
    }

    public function show(TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) return inertia('Forbidden403');

        $technicalVisit->load(['client', 'salesRep', 'technician', 'serviceOrder']);

        // Cargar medios de todas las colecciones de evidencia
        $evidenceMedia = [];
        $collections = ['facade_photo', 'meter_photo', 'meter_prep_photo', 'main_panel_photo', 'secondary_panel_photo', 'additional_evidences', 'documents'];
        foreach ($collections as $collection) {
            $evidenceMedia[$collection] = $technicalVisit->getMedia($collection)->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'original_url' => $media->getUrl(),
                    'mime_type' => $media->mime_type,
                ];
            });
        }

        return Inertia::render('TechnicalVisits/Show', [
            'visit' => $technicalVisit,
            'evidenceMedia' => $evidenceMedia,
        ]);
    }

    public function edit(TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) return inertia('Forbidden403');

        $technicalVisit->load('media');

        return Inertia::render('TechnicalVisits/Edit', [
            'visit' => $technicalVisit,
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => User::role('Ventas')
                ->where('id', '!=', 1)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) return inertia('Forbidden403');

        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'business_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'paternal_surname' => 'nullable|string|max:255',
            'maternal_surname' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'lead_source' => 'nullable|string|max:255',
            
            'sales_rep_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date',
            'status' => 'required|in:Pendiente,Reprogramada,Aceptada,Terminada,Rechazada',
            'reschedule_reason' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            
            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'property_use' => 'nullable|in:Residencial,Comercial,Industrial',
            'requires_long_ladder' => 'boolean',
            'property_floors' => 'nullable|integer',
            'number_of_wires' => 'nullable|integer',
            'voltage' => 'nullable|numeric',
            'google_maps_link' => 'nullable|url',
            
            'road_type' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:50',
            'interior_number' => 'nullable|string|max:50',
            'neighborhood' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            
            'internal_notes' => 'nullable|string',
            
            'system_of_interest' => 'nullable|in:Interconectado,Autónomo,Back-up,Bombeo',
            'module_quantity' => 'nullable|integer',
            'module_brand' => 'nullable|string|max:255',
            'module_capacity' => 'nullable|numeric',
            'budget' => 'nullable|numeric',
            
            'gross_installed_capacity' => 'nullable|numeric',
            'estimated_daily_generation' => 'nullable|numeric',
            'estimated_monthly_generation' => 'nullable|numeric',
            'estimated_monthly_saving' => 'nullable|numeric',
            
            'battery_quantity' => 'nullable|integer',
            'battery_brand' => 'nullable|string|max:255',
            'battery_capacity' => 'nullable|numeric',
            'backup_devices' => 'nullable|array',
            
            'payment_method' => 'nullable|in:Contado,3 MSI,6 MSI,9 MSI,12 MSI,Personalizado',
            'requires_pre_installation' => 'boolean',
            'pre_installation_details' => 'nullable|string',
            'pre_installation_assigned_to' => 'nullable|in:Sun\'s power mx,Cliente,Otro',

            // Archivos
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240',
        ]);

        DB::transaction(function () use ($technicalVisit, $validated, $request) {
            $technicalVisit->update(collect($validated)->except(['documents'])->toArray());

            // Subir archivos nuevos si existen (sin eliminar los existentes)
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $technicalVisit->addMedia($file)->toMediaCollection('documents');
                }
            }
        });

        // return redirect()->route('technical-visits.show', $technicalVisit->id)
        //     ->with('success', 'Visita técnica actualizada correctamente.');
        return redirect()->route('technical-visits.index');
    }

    /**
     * Acciones rápidas desde el Index: Reprogramar, Rechazar, Aceptar o Terminar.
     */
    public function quickUpdate(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:reschedule,reject,accept,complete',
            'scheduled_at' => 'required_if:action,reschedule|date|nullable',
            'reschedule_reason' => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:action,reject|string|max:500|nullable',
        ]);

        if ($validated['action'] === 'reschedule') {
            $technicalVisit->update([
                'scheduled_at' => $validated['scheduled_at'],
                'status' => 'Reprogramada',
                'reschedule_reason' => $validated['reschedule_reason'] ?? null,
            ]);

            return back()->with('success', 'Visita reprogramada correctamente.');
        }

        if ($validated['action'] === 'reject') {
            $technicalVisit->update([
                'status' => 'Rechazada',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            return back()->with('success', 'Visita marcada como rechazada.');
        }

        if ($validated['action'] === 'accept') {
            $technicalVisit->update(['status' => 'Aceptada']);
            return back()->with('success', 'Visita marcada como aceptada.');
        }

        if ($validated['action'] === 'complete') {
            $technicalVisit->update(['status' => 'Terminada']);
            return back()->with('success', 'Visita marcada como terminada.');
        }
    }

    /**
     * Actualiza las notas internas desde el Show.
     */
    public function updateNotes(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'internal_notes' => 'nullable|string',
        ]);

        $technicalVisit->update($validated);

        return back()->with('success', 'Notas internas actualizadas correctamente.');
    }

    /**
     * Sube una evidencia del checklist (fachada, medidor, etc.).
     */
    public function uploadEvidence(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'collection' => 'required|string|in:facade_photo,meter_photo,meter_prep_photo,main_panel_photo,secondary_panel_photo',
            'file' => 'required|file|max:10240',
        ]);

        // Eliminar archivo anterior si existe (singleFile)
        $technicalVisit->clearMediaCollection($validated['collection']);
        $technicalVisit->addMediaFromRequest('file')->toMediaCollection($validated['collection']);

        return back()->with('success', 'Evidencia subida correctamente.');
    }

    /**
     * Sube archivos adicionales genéricos.
     */
    public function uploadAdditionalEvidence(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $technicalVisit->addMediaFromRequest('file')->toMediaCollection('additional_evidences');

        return back()->with('success', 'Archivo adicional subido correctamente.');
    }

    /**
     * Actualiza el voltaje desde el Show.
     */
    public function updateVoltage(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'voltage' => 'nullable|numeric',
        ]);

        $technicalVisit->update($validated);

        return back()->with('success', 'Voltaje actualizado correctamente.');
    }

    /**
     * Actualiza el sistema de interés inline desde el Index o Show.
     */
    public function updateSystemType(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'system_of_interest' => 'nullable|in:Interconectado,Autónomo,Back-up,Bombeo',
        ]);

        $technicalVisit->update($validated);

        return back()->with('success', 'Sistema de interés actualizado.');
    }

    /**
     * Convierte el prospecto de la visita en un Cliente.
     * Crea el registro en clients, el contacto principal, y actualiza la visita.
     */
    public function convertToClient(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Si ya tiene cliente asignado, no duplicar
        if ($technicalVisit->client_id) {
            return back()->with('success', 'Esta visita ya tiene un cliente asignado.');
        }

        $createClient = $request->boolean('create_client', false);
        $taxId = $request->input('tax_id'); // RFC opcional desde el modal

        DB::transaction(function () use ($technicalVisit, $createClient, $branchId, $taxId) {
            // Marcar como terminada
            $technicalVisit->update(['status' => 'Terminada']);

            if ($createClient) {
                // Crear el cliente con los datos de la visita
                $client = Client::create([
                    'branch_id' => $branchId,
                    'name' => $technicalVisit->business_name 
                        ?? trim("{$technicalVisit->first_name} {$technicalVisit->paternal_surname} {$technicalVisit->maternal_surname}"),
                    'tax_id' => $taxId,
                    'type' => 'Cliente',
                    // Dirección
                    'road_type' => $technicalVisit->road_type,
                    'street' => $technicalVisit->street,
                    'exterior_number' => $technicalVisit->exterior_number,
                    'interior_number' => $technicalVisit->interior_number,
                    'neighborhood' => $technicalVisit->neighborhood,
                    'municipality' => $technicalVisit->municipality,
                    'state' => $technicalVisit->state,
                    'zip_code' => $technicalVisit->zip_code,
                    'country' => $technicalVisit->country ?? 'México',
                    'notes' => $technicalVisit->internal_notes,
                ]);

                // Crear el contacto principal en la tabla polimórfica
                $contactPersonName = trim("{$technicalVisit->first_name} {$technicalVisit->paternal_surname} {$technicalVisit->maternal_surname}");
                if ($contactPersonName || $technicalVisit->phone) {
                    $client->contacts()->create([
                        'branch_id' => $branchId,
                        'name' => $contactPersonName ?: ($technicalVisit->business_name ?? 'Contacto Principal'),
                        'phone' => $technicalVisit->phone,
                        'is_primary' => true,
                    ]);
                }

                // Asignar el cliente a la visita
                $technicalVisit->update(['client_id' => $client->id]);
            }
        });

        $message = 'Visita marcada como terminada.';
        if ($createClient) {
            $message = 'Visita terminada y cliente creado exitosamente.';
        }

        return back()->with('success', $message);
    }

    /**
     * Crea una Orden de Servicio a partir de una Visita Técnica.
     * Mapea los campos disponibles y dispara la generación de tareas/evidencias/productos.
     */
    public function createServiceOrder(Request $request, TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Validar que tenga cliente asignado
        if (!$technicalVisit->client_id) {
            return back()->with('error', 'Primero debes crear o asignar un cliente a esta visita.');
        }

        // Validar que no tenga ya una orden de servicio
        if ($technicalVisit->service_order_id) {
            return back()->with('success', 'Esta visita ya tiene una orden de servicio asignada.');
        }

        // Campos de propuesta comercial desde el modal
        $paymentMethod = $request->input('payment_method');
        $downPayment = $request->input('down_payment');
        $requiresPreInstallation = $request->boolean('requires_pre_installation');
        $preInstallationAssignedTo = $request->input('pre_installation_assigned_to');
        $preInstallationDetails = $request->input('pre_installation_details');

        // Mapear voltage a formato válido para service_orders
        $voltageValue = null;
        if ($technicalVisit->voltage) {
            $v = (float) $technicalVisit->voltage;
            if ($v <= 150) $voltageValue = '110V';
            elseif ($v <= 300) $voltageValue = '220V';
            else $voltageValue = '440V';
        }

        $serviceOrder = DB::transaction(function () use ($technicalVisit, $branchId, $voltageValue, $paymentMethod, $downPayment, $requiresPreInstallation, $preInstallationAssignedTo, $preInstallationDetails) {
            $order = ServiceOrder::create([
                'branch_id' => $branchId,
                'client_id' => $technicalVisit->client_id,
                'sales_rep_id' => $technicalVisit->sales_rep_id,
                'status' => 'Aceptado',
                'total_amount' => $technicalVisit->budget ?? 0,
                'down_payment' => $downPayment,
                'service_number' => $technicalVisit->service_number,
                'rate_type' => $technicalVisit->rate_type,
                'system_type' => $technicalVisit->system_of_interest,
                'voltage' => $voltageValue,
                'number_of_wires' => $technicalVisit->number_of_wires,
                'number_of_units' => $technicalVisit->module_quantity,
                'unit_capacity' => $technicalVisit->module_capacity,
                'total_capacity' => $technicalVisit->gross_installed_capacity,
                // Propuesta comercial
                'payment_method' => $paymentMethod,
                'requires_pre_installation' => $requiresPreInstallation,
                'pre_installation_details' => $preInstallationDetails,
                'pre_installation_assigned_to' => $preInstallationAssignedTo,
                // Dirección de instalación mapeada desde la visita
                'installation_street' => $technicalVisit->street,
                'installation_exterior_number' => $technicalVisit->exterior_number,
                'installation_interior_number' => $technicalVisit->interior_number,
                'installation_neighborhood' => $technicalVisit->neighborhood,
                'installation_municipality' => $technicalVisit->municipality,
                'installation_state' => $technicalVisit->state,
                'installation_zip_code' => $technicalVisit->zip_code,
                'installation_country' => $technicalVisit->country ?? 'México',
                'notes' => $technicalVisit->internal_notes,
            ]);

            // Asignar la orden a la visita
            $technicalVisit->update(['service_order_id' => $order->id]);

            return $order;
        });

        // Sincronizar tareas, evidencias y productos según el system_type
        \App\Http\Controllers\ServiceOrderController::syncSystemTypeData($branchId);

        return back()->with([
            'success' => 'Orden de servicio #' . $serviceOrder->id . ' creada exitosamente.',
            'new_service_order_id' => $serviceOrder->id,
        ]);
    }

    public function destroy(TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) return inertia('Forbidden403');

        $technicalVisit->delete();

        return redirect()->route('technical-visits.index')
            ->with('success', 'Visita técnica eliminada.');
    }
}