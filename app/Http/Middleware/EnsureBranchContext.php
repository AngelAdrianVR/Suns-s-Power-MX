<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Context; // Nuevo en Laravel 11/12

class EnsureBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // 1. Si no hay sucursal en sesión, tomamos la del usuario por defecto
            if (!Session::has('current_branch_id')) {
                Session::put('current_branch_id', auth()->user()->branch_id ?? 1);
            }

            // 2. Establecemos el contexto global para que sea accesible en toda la app
            // Esto es más limpio que llamar a session() dentro de los modelos
            Context::add('branch_id', Session::get('current_branch_id'));
        }

        return $next($request);
    }
}