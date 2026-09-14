<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentInstallment;
use Illuminate\Console\Command;

class FixAnticipoLinkedInstallments extends Command
{
    /**
     * Corrige un bug histórico donde el pago de ANTICIPO (enganche) de una orden
     * quedaba vinculado como "pago" de TODAS las mensualidades proyectadas,
     * haciéndolas aparecer como pagadas aunque el saldo seguía pendiente.
     *
     * El anticipo SIEMPRE debe quedar como pago único (descuenta el saldo total)
     * y NUNCA debe marcar cuotas como pagadas, porque las cuotas se generan
     * sobre el SALDO RESTANTE después del anticipo.
     */
    protected $signature = 'payments:fix-anticipo-links {--dry-run : Muestra los cambios sin aplicarlos}';

    protected $description = 'Desvincula las cuotas que un pago de ANTICIPO marcó como pagadas por error y normaliza la etiqueta de esos pagos.';

    public function handle(): int
    {
        // 1) Normalizar la etiqueta de pagos de anticipo (datos legacy en MAYÚSCULAS)
        $toNormalize = Payment::whereRaw('LOWER(notes) = ?', ['anticipo'])->get();
        foreach ($toNormalize as $payment) {
            if ($payment->notes === 'Anticipo') {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] Pago #{$payment->id}: notas '{$payment->notes}' -> 'Anticipo'");
                continue;
            }

            $payment->update(['notes' => 'Anticipo']);
            $this->line("  Pago #{$payment->id}: notas normalizadas a 'Anticipo'.");
        }

        // 2) Desvincular cuotas marcadas como pagadas por un pago de anticipo
        $anticipoPayments = Payment::where('notes', 'Anticipo')->get();
        $found = false;

        foreach ($anticipoPayments as $payment) {
            $installments = PaymentInstallment::where('service_order_id', $payment->service_order_id)
                ->where('payment_id', $payment->id)
                ->get();

            if ($installments->isEmpty()) {
                continue;
            }

            $found = true;
            $this->info("Pago de anticipo #{$payment->id} (orden #{$payment->service_order_id}, \${$payment->amount}) está vinculado a {$installments->count()} cuota(s).");

            foreach ($installments as $installment) {
                if ($this->option('dry-run')) {
                    $this->line("  [dry-run] Cuota #{$installment->id} '{$installment->label}': se desvinculará y volverá a pendiente.");
                    continue;
                }

                $installment->update([
                    'payment_id' => null,
                    'status' => 'pending',
                    'paid_amount' => 0,
                    'paid_date' => null,
                ]);
                $installment->recalculateStatus();

                $this->line("  Cuota #{$installment->id} '{$installment->label}' desvinculada -> estado '{$installment->fresh()->status}'.");
            }
        }

        if (! $found && ! $this->option('dry-run')) {
            $this->info('No se encontraron cuotas vinculadas a pagos de anticipo.');
        }

        $this->info('Proceso terminado.');

        return self::SUCCESS;
    }
}
