<?php

namespace App\Http\Controllers;

use App\Models\TechnicalVisit;
use App\Models\Client;
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
                    'address' => $visit->full_address,
                    'municipality' => $visit->municipality,
                    'state' => $visit->state,
                    'system_of_interest' => $visit->system_of_interest,
                    'scheduled_at' => $visit->scheduled_at?->format('d/m/Y H:i'),
                    'requires_long_ladder' => $visit->requires_long_ladder,
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
            'sales_reps' => User::where('branch_id', $branchId)->where('is_active', true)->get(['id', 'name']),
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
            'Maps_link' => 'nullable|url',
            
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

        return Inertia::render('TechnicalVisits/Show', [
            'visit' => $technicalVisit
        ]);
    }

    public function edit(TechnicalVisit $technicalVisit)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($technicalVisit->branch_id !== $branchId) return inertia('Forbidden403');

        return Inertia::render('TechnicalVisits/Edit', [
            'visit' => $technicalVisit,
            'clients' => Client::where('branch_id', $branchId)->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => User::where('branch_id', $branchId)->where('is_active', true)->get(['id', 'name']),
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
            
            'service_number' => 'nullable|string|max:255',
            'rate_type' => 'nullable|string|max:50',
            'property_use' => 'nullable|in:Residencial,Comercial,Industrial',
            'requires_long_ladder' => 'boolean',
            'property_floors' => 'nullable|integer',
            'number_of_wires' => 'nullable|integer',
            'Maps_link' => 'nullable|url',
            
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
        ]);

        DB::transaction(function () use ($technicalVisit, $validated) {
            $technicalVisit->update($validated);
        });

        // return redirect()->route('technical-visits.show', $technicalVisit->id)
        //     ->with('success', 'Visita técnica actualizada correctamente.');
        return redirect()->route('technical-visits.index');
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