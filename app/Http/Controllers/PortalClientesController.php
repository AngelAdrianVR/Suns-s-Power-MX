<?php

namespace App\Http\Controllers;

use App\Models\PortalPayment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PortalClientesController extends Controller
{
    /**
     * Hub del portal de clientes.
     *
     * Muestra las tarjetas de los módulos disponibles (por ahora solo
     * "Pagos"). Cada tarjeta resume el estado del módulo y enlaza a su
     * listado correspondiente.
     */
    public function index()
    {
        // Sucursal actual (misma lógica que el Dashboard)
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        // --- Pagos: abonos del portal pendientes de validar ---
        // Solo se calcula para usuarios con permiso validar_abonos.
        $pagos = null;

        if (Auth::user()->can('validar_abonos')) {
            $pendingQuery = PortalPayment::query()
                ->where('status', PortalPayment::STATUS_IN_REVIEW)
                ->where('branch_id', $branchId);

            $pagos = [
                'pending_count' => (clone $pendingQuery)->count(),
                'pending_amount' => (float) (clone $pendingQuery)->sum('amount'),
            ];
        }

        return Inertia::render('PortalClientes/Index', [
            'pagos' => $pagos,
        ]);
    }
}
