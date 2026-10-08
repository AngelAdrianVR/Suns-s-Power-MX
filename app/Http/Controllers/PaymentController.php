<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentInstallment;
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
            ->withSum('payments as total_interest', 'interest_amount')
            ->get()
            ->map(function ($order) {
                // El interés moratorio no descuenta el saldo
                $paid = ($order->payments_sum_amount ?? 0) - ($order->total_interest ?? 0);
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
            'method' => 'required|string|in:Efectivo,Transferencia,Tarjeta,Cheque,Depósito,Otro',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'installment_number' => 'nullable|integer|min:1',
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', 
        ]);

        return DB::transaction(function () use ($validated, $request, $branchId) {
            $order = ServiceOrder::findOrFail($validated['service_order_id']);

            // Crear el registro de pago
            $payment = Payment::create([
                'client_id' => $validated['client_id'],
                'service_order_id' => $validated['service_order_id'],
                'installment_number' => $validated['installment_number'] ?? null,
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

            // Vincular el pago con la cuota proyectada si existe installment_number
            if (!empty($validated['installment_number'])) {
                $installment = $order->paymentInstallments()
                    ->where('installment_number', $validated['installment_number'])
                    ->first();
                if ($installment && !$installment->payment_id) {
                    $installment->markAsPaid($payment);
                }
            }

            return redirect()->back()->with('success', 'Abono registrado y comprobante guardado correctamente.');
        });
    }

    /**
     * Actualiza un abono registrado: fecha, monto, notas y comprobante.
     *
     * Solo usuarios con rol Admin pueden editar abonos existentes.
     *  - Si el pago está vinculado a cuota(s) proyectada(s), se sincronizan su
     *    paid_date/paid_amount y se recalcula su estatus (a tiempo / extemporáneo / incumplido).
     *  - Si se sube un comprobante nuevo, reemplaza al anterior.
     */
    public function update(Request $request, Payment $payment)
    {
        $user = $request->user();

        // Solo rol Admin
        abort_unless($user && $user->hasRole('Admin'), 403, 'Solo el rol Admin puede editar los abonos.');

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        if ($payment->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para editar este pago.');
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        DB::transaction(function () use ($payment, $validated, $request) {
            $amount = round((float) $validated['amount'], 2);

            // El interés moratorio no puede superar el monto del abono (el capital no puede ser negativo)
            $interest = min((float) $payment->interest_amount, $amount);

            $payment->update([
                'payment_date' => $validated['payment_date'],
                'amount' => $amount,
                'interest_amount' => $interest,
                'notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null,
            ]);

            // Sincronizar fecha y monto en las cuotas vinculadas y recalcular su estatus
            $installments = PaymentInstallment::where('payment_id', $payment->id)->get();
            foreach ($installments as $installment) {
                $installment->update([
                    'paid_date' => $validated['payment_date'],
                    'paid_amount' => $amount,
                ]);
                $installment->recalculateStatus();
            }

            // Un comprobante nuevo reemplaza al anterior
            if ($request->hasFile('proof')) {
                $payment->clearMediaCollection('receipts');
                $payment->addMediaFromRequest('proof')->toMediaCollection('receipts');
            }
        });

        $payment = $payment->fresh();

        return response()->json([
            'success' => true,
            'amount' => (float) $payment->amount,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'message' => 'Abono actualizado correctamente.',
        ]);
    }

    /**
     * Elimina un abono registrado.
     *
     * Requiere permiso "payments.delete". La eliminación:
     *  - Desvincula TODAS las cuotas proyectadas que fueron marcadas como pagadas
     *    por este abono (vuelven a pendiente/próxima según su fecha), permitiendo
     *    volver a registrar el pago correctamente.
     *  - Elimina el comprobante/evidencia adjunto (media library).
     *  - Elimina el registro del pago, actualizando el saldo de la orden.
     */
    public function destroy(Request $request, Payment $payment)
    {
        $user = $request->user();

        // Permiso requerido: payments.delete (o payments.edit)
        abort_unless(
            $user && ($user->hasPermissionTo('payments.delete') || $user->hasPermissionTo('payments.edit')),
            403,
            'No tienes permiso para eliminar abonos.'
        );

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        if ($payment->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para revertir este pago.');
        }

        DB::transaction(function () use ($payment) {
            // 1) Desvincular TODAS las cuotas proyectadas que referencia este pago
            $payment->load('serviceOrder');
            if ($payment->serviceOrder) {
                $installments = $payment->serviceOrder->paymentInstallments()
                    ->where('payment_id', $payment->id)
                    ->get();

                foreach ($installments as $installment) {
                    $installment->update([
                        'payment_id' => null,
                        'status' => 'pending',
                        'paid_amount' => 0,
                        'paid_date' => null,
                    ]);
                    $installment->recalculateStatus();
                }
            }

            // 2) Eliminar la evidencia / comprobante del abono (media library)
            foreach (['receipts', 'payments', 'default'] as $collection) {
                if ($payment->getMedia($collection)->isNotEmpty()) {
                    $payment->clearMediaCollection($collection);
                }
            }

            // 3) Eliminar el registro del abono
            $payment->delete();
        });

        return redirect()->back()->with(
            'success',
            'Abono eliminado correctamente. Se eliminó el registro junto con su comprobante y las cuotas vinculadas volvieron a quedar pendientes.'
        );
    }
}