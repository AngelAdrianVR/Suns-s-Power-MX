<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * API Endpoint: Obtiene las órdenes de servicio con saldo pendiente de un cliente.
     * Se llama vía Axios desde el Modal de Pagos.
     */
    public function getPendingOrders(Client $client)
    {
        // Seguridad: Verificar Branch
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $orders = ServiceOrder::where('client_id', $client->id)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['Cancelado', 'Cotización']) // Ignorar canceladas
            ->withSum('payments', 'amount') // Sumar pagos previos
            ->get()
            ->map(function ($order) {
                $paid = $order->payments_sum_amount ?? 0;
                $debt = $order->total_amount - $paid;

                return [
                    'id' => $order->id,
                    'identifier' => "OS-#{$order->id} - " . $order->created_at->format('d/m/Y'), // Etiqueta para el Select
                    'total_amount' => (float) $order->total_amount,
                    'paid_amount' => (float) $paid,
                    'pending_balance' => (float) $debt,
                    'status' => $order->status
                ];
            })
            // Solo retornamos órdenes que tengan deuda mayor a $1 (tolerancia decimales)
            ->filter(fn($o) => $o['pending_balance'] > 1)
            ->values();

        return response()->json($orders);
    }

    /**
     * Registra un nuevo abono.
     */
    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'service_order_id' => [
                'required',
                Rule::exists('service_orders', 'id')->where(function ($query) use ($request) {
                    return $query->where('client_id', $request->client_id);
                }),
            ],
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'method' => 'required|string|in:Efectivo,Transferencia,Tarjeta,Cheque,Otro',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        // Validación de negocio: No pagar más de lo que se debe en la orden
        $order = ServiceOrder::findOrFail($validated['service_order_id']);
        $currentPaid = $order->payments()->sum('amount');
        $newBalance = $order->total_amount - ($currentPaid + $validated['amount']);

        // Permitimos un margen de error de centavos, o puedes bloquear si newBalance < -1
        if ($newBalance < -1) {
             return back()->withErrors(['amount' => 'El monto excede el saldo pendiente de la orden.']);
        }

        Payment::create([
            ...$validated,
            'branch_id' => $branchId,
        ]);

        // Actualizar estatus de la orden si se liquidó (Opcional, regla de negocio)
        if ($newBalance <= 1 && $order->status !== 'Completado') {
             // Podrías cambiar el estado a 'Pagado' o 'Facturado' aquí si tu flujo lo requiere
        }

        return back()->with('success', 'Abono registrado correctamente.');
    }
}