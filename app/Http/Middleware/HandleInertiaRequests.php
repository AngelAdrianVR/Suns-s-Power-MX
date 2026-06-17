<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Branch; // Asegúrate de importar tu modelo Branch

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            
            'auth' => [
                'user' => $user,
                
                // Enviamos los roles (ej: ['admin', 'vendedor'])
                'roles' => $user ? $user->getRoleNames() : [],
                
                // Enviamos TODOS los permisos directos y heredados (ej: ['crear_producto', 'ver_reportes'])
                // pluck('name') extrae solo el string del nombre para no enviar objetos pesados
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],

                // Enviamos la sucursal actual seleccionada en la sesión
                'current_branch' => $request->session()->get('current_branch_id'),
            ],

            // Lista de sucursales para el dropdown (solo si hay usuario logueado)
            // Filtramos solo id y nombre por seguridad y rendimiento
            'branches' => $user 
                ? Branch::select('id', 'name')->where('is_active', true)->get() 
                : [],

            // Compartir datos flasheados (success, error, new_service_order_id, etc.)
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'new_service_order_id' => $request->session()->get('new_service_order_id'),
            ],

            // Inyectamos las últimas notificaciones
            'notifications' => $user 
                ? $user->notifications()->latest()->take(20)->get() 
                : [],
        ];
    }
}