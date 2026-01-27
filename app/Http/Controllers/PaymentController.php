<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * API Endpoint: Obtiene las órdenes de servicio con saldo pendiente de un cliente.
     */
    public function getPendingOrders(Client $client)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($client->branch_id !== $branchId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $orders = ServiceOrder::where('client_id', $client->id)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['Cancelado', 'Cotización'])
            ->withSum('payments', 'amount')
            ->get()
            ->map(function ($order) {
                $paid = $order->payments_sum_amount ?? 0;
                $debt = $order->total_amount - $paid;

                return [
                    'id' => $order->id,
                    'identifier' => "OS-#{$order->id} - " . $order->created_at->format('d/m/Y'),
                    'total_amount' => (float) $order->total_amount,
                    'paid_amount' => (float) $paid,
                    'pending_balance' => (float) $debt,
                    'status' => $order->status
                ];
            })
            ->filter(fn($o) => $o['pending_balance'] > 1)
            ->values();

        return response()->json($orders);
    }

    /**
     * Registra un nuevo abono con comprobante obligatorio.
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
            // NUEVO: Validación de archivo obligatorio
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', 
        ]);

        return DB::transaction(function () use ($validated, $request, $branchId) {
            $order = ServiceOrder::findOrFail($validated['service_order_id']);
            $currentPaid = $order->payments()->sum('amount');
            $newBalance = $order->total_amount - ($currentPaid + $validated['amount']);

            if ($newBalance < -1) {
                return back()->withErrors(['amount' => 'El monto excede el saldo pendiente de la orden.']);
            }

            // Crear el registro de pago
            $payment = Payment::create([
                'client_id' => $validated['client_id'],
                'service_order_id' => $validated['service_order_id'],
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => $validated['method'],
                'reference' => $validated['reference'],
                'notes' => $validated['notes'],
                'branch_id' => $branchId,
            ]);

            // Adjuntar el comprobante usando Media Library
            if ($request->hasFile('proof')) {
                $payment->addMediaFromRequest('proof')
                    ->toMediaCollection('receipts');
            }

            return redirect()->back()->with('success', 'Abono registrado y comprobante guardado correctamente.');
        });
    }
}