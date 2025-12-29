<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redirect;

class UserController extends Controller
{
    /**
     * Muestra la lista de usuarios.
     */
    public function index(Request $request)
    {
        // Obtenemos el término de búsqueda si existe
        $search = $request->input('search');

        $users = User::query()
            ->with('branch') // Cargamos la relación de sucursal para mostrarla en la tabla
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
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
        // Obtenemos las sucursales activas para el selector
        // Asumimos que quieres mostrar 'name' y usar 'id' como valor
        $branches = Branch::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Create', [
            'branches' => $branches
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'branch_id' => ['required', 'exists:branches,id'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'], // Máx 10MB por archivo
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null, // Guardamos el teléfono
            'password' => Hash::make($validated['password']),
            'branch_id' => $validated['branch_id'],
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
            'message' => 'Usuario creado exitosamente.'
        ]);
    }

    public function show(User $user)
    {        
        // Cargamos la sucursal
        $user->load(['branch', 'media']);
        
        // Cargamos las últimas 20 tareas asignadas (relación belongsToMany)
        // Asumiendo que la relación 'tasks' está definida en el modelo User
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

        $user->load('branch', 'media');
        // Necesitamos las sucursales para el selector en la edición también
        $branches = Branch::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'branches' => $branches
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Ignoramos el ID del usuario actual para permitir que mantenga su email
            'email' => [
                'required', 
                'string', 
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => ['nullable', 'string', 'max:20'], // Nuevo campo validado
            'branch_id' => ['required', 'exists:branches,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? $user->phone; // Actualizamos el teléfono
        $user->branch_id = $validated['branch_id'];

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
        // Evitar que el usuario se desactive a sí mismo si es el único admin, etc.
        
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