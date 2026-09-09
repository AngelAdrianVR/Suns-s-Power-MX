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
     * Aprobar abono: crea el pago real en el ERP y copia el comprobante.
     */
    public function approve(Request $request, PortalPayment $portalPayment)
    {
        if ($portalPayment->status !== 'En revisión') {
            return back()->with('error', 'Este abono ya fue procesado.');
        }

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

            $receipt = $portalPayment->getFirstMedia('receipts');

            if ($receipt) {
                $absolutePath = Storage::disk('erp_media')->path($receipt->getPath());

                if (is_file($absolutePath)) {
                    $payment
                        ->addMedia($absolutePath)
                        ->preservingOriginal()
                        ->usingFileName($receipt->file_name)
                        ->toMediaCollection('receipts');
                }
            }

            $portalPayment->update([
                'status' => 'Completado',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
        });

        return back()->with('success', 'Abono aprobado y registrado como pago.');
    }

    /**
     * Rechazar abono con motivo.
     */
    public function reject(Request $request, PortalPayment $portalPayment)
    {
        if ($portalPayment->status !== 'En revisión') {
            return back()->with('error', 'Este abono ya fue procesado.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $portalPayment->update([
            'status' => 'Rechazado',
            'rejection_reason' => $validated['rejection_reason'],
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        return back()->with('success', 'Abono rechazado.');
    }
}
