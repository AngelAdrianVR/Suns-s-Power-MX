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
use Spatie\Permission\Models\Role; // Importamos el modelo Role de Spatie

class UserController extends Controller
{
    /**
     * Muestra la lista de usuarios de la sucursal actual.
     */
    public function index(Request $request)
    {
        // Obtenemos el término de búsqueda si existe
        $search = $request->input('search');
        
        // Recuperamos la sucursal actual de la sesión o del usuario autenticado
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $users = User::query()
            ->where('branch_id', $branchId) // Filtramos por la sucursal actual
            ->where('id', '!=', 1) // Ocultamos al usuario de soporte (ID 1)
            ->with(['branch', 'roles']) // Cargamos roles y sucursal
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
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
        // Obtenemos los roles y los mapeamos para el Select de Naive UI
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
        // Validamos los datos
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'], // Validación del rol
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ]);

        // Obtenemos el ID de la sucursal actual
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        // Asignamos el rol utilizando Spatie
        $user->assignRole($validated['role']);

        // Procesar archivos
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $user->addMedia($file)->toMediaCollection('documents');
            }
        }

        return redirect()->route('users.index')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario creado exitosamente con rol asignado.'
        ]);
    }

    public function show(User $user)
    {        
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para ver este usuario.');
        }

        $user->load(['branch', 'media', 'roles']);
        
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

        // Cargamos roles actuales del usuario
        $user->load(['branch', 'media', 'roles']);
        
        // Obtenemos los roles para el select
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 
                'string', 
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'exists:roles,name'], // Validación del rol
            'password' => ['nullable', 'string', 'min:8'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? $user->phone;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sincronizamos el rol (quita los anteriores y pone el nuevo)
        $user->syncRoles([$validated['role']]);

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $user->addMedia($file)->toMediaCollection('documents');
            }
        }

        return redirect()->route('users.show', $user->id)->with('flash', [
            'type' => 'success',
            'message' => 'Usuario y rol actualizados correctamente.'
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
        //
    }
}