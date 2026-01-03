<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Muestra la vista principal de gestión de roles y permisos.
     */
    public function index()
    {
        // Solo el usuario ID 1 puede ver/gestionar permisos crudos
        $isDeveloper = Auth::id() === 1;

        $roles = Role::with('permissions')->orderBy('id')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('id'), // Solo IDs para el formulario
                'created_at' => $role->created_at->format('d/m/Y'),
            ];
        });

        // Agrupamos permisos por 'module' para mostrarlos ordenados en el UI
        $permissions = [];
        if ($isDeveloper) {
            $allPermissions = Permission::all();
            foreach ($allPermissions as $perm) {
                // Asumiendo que tu tabla permissions tiene columna 'module' según tu migración
                $module = $perm->module ?? 'General'; 
                $permissions[$module][] = [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'description' => $perm->description ?? $perm->name,
                ];
            }
        }

        return Inertia::render('Setting/Role/Index', [
            'roles' => $roles,
            'groupedPermissions' => $permissions,
            'isDeveloper' => $isDeveloper,
        ]);
    }

    /**
     * Guarda un nuevo rol.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'], // IDs de permisos
            'permissions.*' => ['exists:permissions,id']
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        // Asignar permisos si se enviaron y si es developer
        if (Auth::id() === 1 && !empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->back()->with('success', 'Rol creado correctamente.');
    }

    /**
     * Actualiza un rol existente.
     */
    public function update(Request $request, Role $role)
    {
        // Evitar editar roles críticos si es necesario, o bloquear al admin principal
        // if ($role->id === 1) return back()->with('error', 'No puedes editar el rol Super Admin.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id']
        ]);

        $role->update(['name' => $validated['name']]);

        // Sincronizar permisos (Solo Developer)
        if (Auth::id() === 1) {
            // Si el array existe en el request (aunque esté vacío), sincronizamos
            if ($request->has('permissions')) {
                $role->syncPermissions($validated['permissions']);
            }
        }

        return redirect()->back()->with('success', 'Rol actualizado correctamente.');
    }

    /**
     * Elimina un rol.
     */
    public function destroy(Role $role)
    {
        if ($role->id === 1) { // Protección básica
            return back()->with('error', 'No puedes eliminar el rol principal.');
        }
        
        // Verificar si hay usuarios con este rol
        if ($role->users()->count() > 0) {
            return back()->with('error', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->back()->with('success', 'Rol eliminado correctamente.');
    }
    
    // --------------------------------------------------------------------------------
    // CRUD DE PERMISOS (SOLO DESARROLLADOR - ID 1)
    // --------------------------------------------------------------------------------
    
    public function storePermission(Request $request)
    {
        if (Auth::id() !== 1) abort(403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:permissions,name'],
            'description' => ['required', 'string'],
            'module' => ['required', 'string'],
        ]);

        Permission::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'module' => $validated['module'],
            'guard_name' => 'web'
        ]);

        return back()->with('success', 'Permiso creado.');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        if (Auth::id() !== 1) abort(403);

        $validated = $request->validate([
            'name' => ['required', 'string', Rule::unique('permissions', 'name')->ignore($permission->id)],
            'description' => ['required', 'string'],
            'module' => ['required', 'string'],
        ]);

        $permission->update($validated);

        return back()->with('success', 'Permiso actualizado.');
    }

    public function destroyPermission(Permission $permission)
    {
        if (Auth::id() !== 1) abort(403);
        $permission->delete();
        return back()->with('success', 'Permiso eliminado.');
    }
}