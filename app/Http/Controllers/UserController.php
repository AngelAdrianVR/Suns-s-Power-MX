<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Importante para transacciones
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Muestra la lista de usuarios de la sucursal actual.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $users = User::query()
            ->where('branch_id', $branchId)
            ->where('id', '!=', 1) 
            ->with(['branch', 'roles'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('curp', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create()
    {
        $roles = Role::all()->map(function ($role) {
            return [
                'label' => $role->name,
                'value' => $role->name
            ];
        });

        return Inertia::render('Users/Create', [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validaciones
        $validated = $request->validate([
            // Generales
            'first_name' => 'required|string|max:100',
            'paternal_surname' => 'required|string|max:100',
            'maternal_surname' => 'nullable|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',

            // Legales
            'birth_date' => 'nullable|date',
            'curp' => 'nullable|string|max:18',
            'rfc' => 'nullable|string|max:13',
            'nss' => 'nullable|string|max:11',

            // Domicilio
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'municipality' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'address_references' => 'nullable|string',
            'cross_streets' => 'nullable|string',

            // Bancarios
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:100',
            'bank_clabe' => 'nullable|string|max:18',
            'bank_account_number' => 'nullable|string|max:20',

            // Listas
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240',
            
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.first_name' => 'required|string',
            'beneficiaries.*.paternal_surname' => 'required|string',
            'beneficiaries.*.relationship' => 'required|string',

            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string',
            'contacts.*.relationship' => 'required|string',
            'contacts.*.phone' => 'required|string',
        ]);

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        DB::transaction(function () use ($validated, $request, $branchId) {
            
            // Construir nombre completo legacy
            $fullName = trim("{$validated['first_name']} {$validated['paternal_surname']} " . ($validated['maternal_surname'] ?? ''));

            // Preparar datos usuario
            $userData = collect($validated)->except(['contacts', 'beneficiaries', 'role', 'documents'])->toArray();
            $userData['name'] = $fullName;
            $userData['password'] = Hash::make($validated['password']);
            $userData['branch_id'] = $branchId;
            $userData['is_active'] = true;

            // Crear Usuario
            $user = User::create($userData);

            // Asignar Rol
            $user->assignRole($validated['role']);

            // Guardar Beneficiarios
            if (!empty($validated['beneficiaries'])) {
                $user->beneficiaries()->createMany($validated['beneficiaries']);
            }

            // Guardar Contactos Emergencia (Polimórficos)
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $user->contacts()->create([
                        'branch_id' => $branchId,
                        'name' => $contact['name'],
                        'job_title' => $contact['relationship'], // Usamos job_title para parentesco
                        'phone' => $contact['phone'],
                        'is_primary' => false,
                        'notes' => 'Contacto de Emergencia'
                    ]);
                }
            }

            // Procesar archivos
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $user->addMedia($file)->toMediaCollection('documents');
                }
            }
        });

        return redirect()->route('users.index')->with('flash', [
            'type' => 'success',
            'message' => 'Expediente creado exitosamente.'
        ]);
    }

    public function show(User $user)
    {        
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para ver este usuario.');
        }

        // Cargar todas las relaciones necesarias para el expediente
        $user->load(['branch', 'media', 'roles', 'beneficiaries', 'contacts']);
        
        $lastTasks = $user->tasks()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return Inertia::render('Users/Show', [
            'user' => $user,
            'lastTasks' => $lastTasks
        ]);
    }

    public function edit($user)
    {
        $user = User::findOrFail($user);
        
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para editar este usuario.');
        }

        // Cargamos relaciones
        $user->load(['branch', 'media', 'roles', 'beneficiaries', 'contacts']);
        
        $roles = Role::all()->map(function ($role) {
            return [
                'label' => $role->name,
                'value' => $role->name
            ];
        });
        
        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para actualizar este usuario.');
        }

        $validated = $request->validate([
            // Generales
            'first_name' => 'required|string|max:100',
            'paternal_surname' => 'required|string|max:100',
            'maternal_surname' => 'nullable|string|max:100',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|exists:roles,name',
            'password' => 'nullable|string|min:8', // Opcional en update

            // Legales
            'birth_date' => 'nullable|date',
            'curp' => 'nullable|string|max:18',
            'rfc' => 'nullable|string|max:13',
            'nss' => 'nullable|string|max:11',

            // Domicilio
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'municipality' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'address_references' => 'nullable|string',
            'cross_streets' => 'nullable|string',

            // Bancarios
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:100',
            'bank_clabe' => 'nullable|string|max:18',
            'bank_account_number' => 'nullable|string|max:20',

            // Listas
            'documents' => 'nullable|array',
            'beneficiaries' => 'nullable|array',
            'contacts' => 'nullable|array',
        ]);

        DB::transaction(function () use ($validated, $request, $user, $currentBranchId) {
            
            // Actualizar campos directos del usuario
            $fullName = trim("{$validated['first_name']} {$validated['paternal_surname']} " . ($validated['maternal_surname'] ?? ''));
            
            $userData = collect($validated)->except(['contacts', 'beneficiaries', 'role', 'documents', 'password'])->toArray();
            $userData['name'] = $fullName;

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // Sincronizar Rol
            $user->syncRoles([$validated['role']]);

            // Sincronizar Beneficiarios (Borrar y Recrear para consistencia)
            $user->beneficiaries()->delete();
            if (!empty($validated['beneficiaries'])) {
                $user->beneficiaries()->createMany($validated['beneficiaries']);
            }

            // Sincronizar Contactos (Borrar y Recrear)
            $user->contacts()->delete();
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $user->contacts()->create([
                        'branch_id' => $currentBranchId,
                        'name' => $contact['name'],
                        'job_title' => $contact['relationship'],
                        'phone' => $contact['phone'],
                        'is_primary' => false,
                        'notes' => 'Emergencia'
                    ]);
                }
            }

            // Archivos (Solo agregar nuevos)
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $user->addMedia($file)->toMediaCollection('documents');
                }
            }
        });

        return redirect()->route('users.show', $user->id)->with('flash', [
            'type' => 'success',
            'message' => 'Expediente actualizado correctamente.'
        ]);
    }

    public function toggleStatus(User $user)
    {
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'Acción no autorizada.');
        }
        
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activado' : 'desactivado';

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Usuario {$user->name} {$status} correctamente."
        ]);
    }

    public function destroy(User $user)
    {
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para eliminar este usuario.');
        }

        if ($user->id === Auth::id()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No puedes eliminar tu propia cuenta mientras está en uso.'
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario eliminado correctamente.'
        ]);
    }
}