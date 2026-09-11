<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PortalPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalPaymentController extends Controller
{
    /**
     * Listado de abonos registrados desde el portal de clientes.
     */
    public function index()
    {
        $abonos = PortalPayment::query()
            ->with(['client:id,name', 'serviceOrder:id,service_number', 'media'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (PortalPayment $p) => [
                'id' => $p->id,
                'client_name' => $p->client?->name,
                'service_number' => $p->serviceOrder?->service_number,
                'amount' => (float) $p->amount,
                'payment_date' => $p->payment_date?->format('d/m/Y'),
                'method' => $p->method,
                'reference' => $p->reference,
                'notes' => $p->notes,
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
                'created_at' => $p->created_at?->format('d/m/Y H:i'),
                'receipt' => $this->receiptFor($p),
            ])
            ->values();

        return Inertia::render('PortalAbonos/Index', [
            'abonos' => $abonos,
        ]);
    }

    private function receiptFor(PortalPayment $portalPayment): ?array
    {
        $media = $portalPayment->getFirstMedia('receipts');

        if (! $media) {
            return null;
        }

        return [
            'url' => $media->getUrl(),
            'name' => $media->file_name,
        ];
    }

    /**
     * Aprobar abono: crea el pago real en el ERP, copia el comprobante y
     * marca el abono como Completado. Al crear el Payment, el saldo del
     * cliente baja automáticamente (los saldos se calculan con la suma
     * de pagos registrados).
     */
    public function approve(Request $request, PortalPayment $portalPayment)
    {
        if ($portalPayment->status !== PortalPayment::STATUS_IN_REVIEW) {
            return $this->respond($request, 'Este abono ya fue procesado.', 'error', 422);
        }

        // Re-validar que el monto no supere el saldo pendiente ACTUAL del
        // servicio (el cliente pudo haber hecho otros pagos desde el portal
        // o el personal del ERP pudo registrar abonos mientras tanto).
        if ($portalPayment->service_order_id) {
            $order = $portalPayment->serviceOrder()->first();

            if ($order) {
                $balance = $this->outstandingBalance($order);

                if ((float) $portalPayment->amount > $balance + 0.005) {
                    return $this->respond(
                        $request,
                        'No se puede aprobar: el monto del abono supera el saldo pendiente del servicio ('
                            .number_format($balance, 2).').',
                        'error',
                        422
                    );
                }
            }
        }

        try {
            DB::transaction(function () use ($portalPayment, $request) {
                $payment = Payment::create([
                    'branch_id' => $portalPayment->branch_id,
                    'client_id' => $portalPayment->client_id,
                    'service_order_id' => $portalPayment->service_order_id,
                    'amount' => $portalPayment->amount,
                    'interest_amount' => 0,
                    'payment_date' => $portalPayment->payment_date,
                    'method' => $portalPayment->method,
                    'reference' => $portalPayment->reference,
                    'notes' => 'Abono registrado desde el portal de clientes',
                    'portal_payment_id' => $portalPayment->id,
                ]);

                // Copiar el comprobante del abono del portal al pago del ERP
                $receipt = $portalPayment->getFirstMedia('receipts');

                if ($receipt) {
                    $absolutePath = Storage::disk(PortalPayment::RECEIPT_DISK)->path($receipt->getPath());

                    if (is_file($absolutePath)) {
                        $payment
                            ->addMedia($absolutePath)
                            ->preservingOriginal()
                            ->usingFileName($receipt->file_name)
                            ->toMediaCollection('receipts');
                    }
                }

                $portalPayment->update([
                    'status' => PortalPayment::STATUS_COMPLETED,
                    'validated_by' => $request->user()->id,
                    'validated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return $this->respond($request, 'No se pudo aprobar el abono. Inténtalo de nuevo.', 'error', 500);
        }

        return $this->respond($request, 'Abono aprobado y registrado como pago. El saldo del cliente se actualizó.');
    }

    /**
     * Rechazar abono con motivo.
     */
    public function reject(Request $request, PortalPayment $portalPayment)
    {
        if ($portalPayment->status !== PortalPayment::STATUS_IN_REVIEW) {
            return $this->respond($request, 'Este abono ya fue procesado.', 'error', 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $portalPayment->update([
            'status' => PortalPayment::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'],
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        return $this->respond($request, 'Abono rechazado. El cliente podrá ver el motivo en el portal.');
    }

    /**
     * Saldo pendiente real de una orden de servicio:
     * total - (pagos - intereses). El interés moratorio no descuenta saldo.
     */
    private function outstandingBalance($order): float
    {
        $paid = (float) $order->payments()->sum('amount')
            - (float) $order->payments()->sum('interest_amount');

        return max(0, (float) $order->total_amount - $paid);
    }

    /**
     * Respuesta uniforme: JSON para peticiones AJAX/axios y flash
     * redirigiendo de vuelta para peticiones Inertia.
     */
    private function respond(Request $request, string $message, string $type = 'success', int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $type === 'success',
                'message' => $message,
            ], $status);
        }

        return back()->with($type, $message);
    }
}
