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
            ->with('branch') // Cargamos la relación (opcional si ya sabemos la sucursal, pero útil para mostrar el nombre)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest() // Ordenar por creación descendente
            ->paginate(15) // Paginación de 15 elementos
            ->withQueryString(); // Mantener parámetros de búsqueda en la URL

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create()
    {
        // Ya no necesitamos enviar las sucursales porque se asignará automáticamente
        return Inertia::render('Users/Create');
    }

    public function store(Request $request)
    {
        // Validamos los datos (eliminamos branch_id de la validación del request)
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'], // Máx 10MB por archivo
        ]);

        // Obtenemos el ID de la sucursal actual para asignarlo
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'branch_id' => $branchId, // Asignación automática
            'is_active' => true,
        ]);

        // Procesar archivos con Spatie Media Library
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $user->addMedia($file)->toMediaCollection('documents');
            }
        }

        return redirect()->route('users.index')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario creado exitosamente en la sucursal actual.'
        ]);
    }

    public function show(User $user)
    {        
        // Verificación de seguridad: asegurar que el usuario pertenece a la sucursal actual
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para ver este usuario.');
        }

        // Cargamos la sucursal
        $user->load(['branch', 'media']);
        
        // Cargamos las últimas 20 tareas asignadas
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
        
        // Verificación de seguridad
        $currentBranchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($user->branch_id !== $currentBranchId) {
            abort(403, 'No tienes permiso para editar este usuario.');
        }

        $user->load('branch', 'media');
        
        // En Edit también quitamos la selección de sucursales si queremos restringirlo
        // o las enviamos si permites mover usuarios (aquí asumo restricción)
        
        return Inertia::render('Users/Edit', [
            'user' => $user
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Verificación de seguridad
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
            // Eliminamos branch_id de la validación para no permitir cambiarlo vía form
            'password' => ['nullable', 'string', 'min:8'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? $user->phone;
        // No actualizamos branch_id para mantenerlo en su sucursal original
        // o forzamos $currentBranchId si esa es la lógica deseada.

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $user->addMedia($file)->toMediaCollection('documents');
            }
        }

        return redirect()->route('users.show', $user->id)->with('flash', [
            'type' => 'success',
            'message' => 'Usuario actualizado correctamente.'
        ]);
    }

    /**
     * Activa o desactiva un usuario.
     */
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