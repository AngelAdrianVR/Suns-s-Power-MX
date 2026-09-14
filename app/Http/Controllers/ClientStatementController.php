<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PaymentInstallment;
use App\Models\ServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estado de cuenta del cliente (vista en pantalla + descarga en PDF).
 *
 * Réplica del estado de cuenta del portal de clientes (PortalClientesSuns):
 * mismos datos, mismas vistas Blade (`resources/views/pdf/*`) y el mismo
 * PDF generado con DomPDF, para que el archivo descargado sea idéntico.
 */
class ClientStatementController extends Controller
{
    /**
     * Servicios que se incluyen en el estado de cuenta.
     * Son los mismos estatus que muestra el portal de clientes.
     */
    private const VISIBLE_STATUSES = ['Aceptado', 'En Proceso', 'Completado', 'Facturado'];

    /**
     * Vista en pantalla del estado de cuenta (se abre en pestaña nueva).
     * Permite ver un servicio concreto o todos los servicios del cliente.
     */
    public function view(Request $request, Client $client): Response
    {
        $client->load('contacts');

        return Inertia::render('Clients/EstadoCuenta', [
            'client' => $this->clientPayload($client),
            'services' => $this->serviceStatements($client),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Descarga el estado de cuenta de un servicio concreto en PDF.
     */
    public function download(Client $client, ServiceOrder $serviceOrder)
    {
        abort_unless((int) $serviceOrder->client_id === (int) $client->id, 404);

        $serviceOrder->load(['payments', 'paymentInstallments']);

        $payload = $this->serviceStatement($serviceOrder);

        $pdf = Pdf::loadView('pdf.estado-cuenta', [
            'payload' => $payload,
            'client' => $client,
            'generatedAt' => now(),
            // dompdf necesita GD para embeber PNG; sin GD se omite el logotipo.
            'logo' => extension_loaded('gd') ? public_path('images/isologo-suns-power-mx.png') : null,
        ])->setPaper('letter');

        // El nombre del archivo incluye al cliente y el número de servicio.
        $name = 'estado-de-cuenta-'.$this->fileSlug($client)
            .'-'.($serviceOrder->service_number ?: $serviceOrder->id).'.pdf';

        return $pdf->download($name);
    }

    /**
     * Descarga un PDF con el estado de cuenta de TODOS los servicios del cliente.
     */
    public function downloadAll(Client $client)
    {
        $services = $this->serviceStatements($client);

        abort_if(empty($services), 404, 'No hay servicios para generar el estado de cuenta.');

        $pdf = Pdf::loadView('pdf.estado-cuenta-general', [
            'client' => $client,
            'services' => $services,
            'generatedAt' => now(),
            'logo' => extension_loaded('gd') ? public_path('images/isologo-suns-power-mx.png') : null,
        ])->setPaper('letter');

        // El nombre del archivo incluye el nombre del cliente.
        $name = 'estado-de-cuenta-'.$this->fileSlug($client).'.pdf';

        return $pdf->download($name);
    }

    /** Nombre del cliente listo para usarse en el nombre del archivo PDF. */
    private function fileSlug(Client $client): string
    {
        return Str::slug((string) $client->name) ?: 'cliente-'.$client->id;
    }

    /** Datos básicos del cliente para la vista en pantalla. */
    private function clientPayload(Client $client): array
    {
        $mainContact = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'tax_id' => $client->tax_id,
            'full_address' => $client->full_address,
            'email' => $mainContact?->email,
            'phone' => $mainContact?->phone,
        ];
    }

    /** Estado de cuenta (datos) de un solo servicio. */
    private function serviceStatement(ServiceOrder $o): array
    {
        // Saldo real del servicio: total − (pagos − intereses).
        // El interés moratorio cobrado no abona a capital.
        $paidPrincipal = (float) $o->payments->sum('amount') - (float) $o->payments->sum('interest_amount');
        $balance = round(max(0.0, (float) $o->total_amount - $paidPrincipal), 2);

        return [
            'id' => $o->id,
            'service_number' => $o->service_number ?: 'Orden #'.$o->id,
            'system_type' => $o->system_type,
            'status' => $o->status,
            'start_date' => $o->start_date?->format('Y-m-d'),
            'payment_method' => $o->payment_method,
            'installation_address' => $this->installationAddress($o),
            'down_payment' => round((float) $o->down_payment, 2),
            'total_amount' => round((float) $o->total_amount, 2),
            'balance' => $balance,
            'paid' => round(max(0.0, (float) $o->total_amount - $balance), 2),
            'overdue_interest' => round(
                $o->paymentInstallments->sum(fn ($i) => $i->calculateInterest()),
                2
            ),
            'installments' => $o->paymentInstallments
                ->sortBy('installment_number')
                ->values()
                ->map(fn ($i) => [
                    'installment_number' => $i->installment_number,
                    'label' => $i->label,
                    'projected_date' => $i->projected_date?->format('Y-m-d'),
                    'amount' => round((float) $i->amount, 2),
                    'status' => $this->installmentStatus($i),
                    'interest' => $i->calculateInterest(),
                    'total_with_interest' => $i->total_with_interest,
                ])
                ->all(),
            // Del más antiguo al más reciente (mismo orden en pantalla y PDF).
            'payments' => $o->payments
                ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($p) => [
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'amount' => round((float) $p->amount, 2),
                    'interest_amount' => round((float) $p->interest_amount, 2),
                    'method' => $p->method,
                    'reference' => $p->reference,
                ])
                ->all(),
        ];
    }

    /**
     * Dirección de instalación con el mismo formato que usa el portal de
     * clientes (mismo armado de `installation_address` en su modelo).
     */
    private function installationAddress(ServiceOrder $o): string
    {
        $parts = array_filter([
            trim(($o->installation_street ?? '').($o->installation_exterior_number ? ' #'.$o->installation_exterior_number : '')),
            $o->installation_interior_number ? 'Int. '.$o->installation_interior_number : null,
            $o->installation_neighborhood,
            $o->installation_municipality,
            $o->installation_state,
        ]);

        return implode(', ', $parts) ?: 'Sin dirección registrada';
    }

    /**
     * Estatus de una cuota para el estado de cuenta:
     *  - paid: ya pagada (verde)
     *  - due_soon: vence en 7 días o menos (naranja)
     *  - overdue: ya venció (rojo)
     *  - pending: aún no vence y falta más de una semana (texto normal)
     */
    private function installmentStatus(PaymentInstallment $installment): string
    {
        // Misma regla de "pagada" que el portal: cuota con pago o en on_time.
        if ($installment->payment_id !== null || in_array($installment->status, ['paid', 'on_time'], true)) {
            return 'paid';
        }

        $dueDate = $installment->projected_date;

        if (! $dueDate) {
            return 'pending';
        }

        if ($dueDate->lt(today())) {
            return 'overdue';
        }

        return $dueDate->lte(today()->addDays(7)) ? 'due_soon' : 'pending';
    }

    /** Estado de cuenta (datos) por cada servicio visible del cliente. */
    private function serviceStatements(Client $client): array
    {
        return $client->serviceOrders()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->with(['payments', 'paymentInstallments'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServiceOrder $o) => $this->serviceStatement($o))
            ->values()
            ->all();
    }
}
