<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentInstallment extends Model
{
    /** Tasa de interés moratorio: 10% mensual */
    public const MONTHLY_INTEREST_RATE = 0.10;

    /** Días de gracia antes de aplicar interés */
    public const GRACE_PERIOD_DAYS = 5;

    protected $fillable = [
        'service_order_id',
        'installment_number',
        'label',
        'projected_date',
        'amount',
        'apply_interest',
        'status',
        'paid_amount',
        'paid_date',
        'payment_id',
    ];

    protected $casts = [
        'projected_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'apply_interest' => 'boolean',
    ];

    /**
     * Relación con la orden de servicio.
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * Relación con el pago real (si ya se pagó).
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Marcar esta cuota como pagada.
     */
    public function markAsPaid(Payment $payment): void
    {
        $this->update([
            'status' => 'paid',
            'paid_amount' => $payment->amount,
            'paid_date' => $payment->payment_date,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Recalcular estatus automáticamente basado en la fecha y si hay pago.
     */
    public function recalculateStatus(): void
    {
        if ($this->payment_id || $this->status === 'paid') {
            // Ya pagada - determinar si fue a tiempo o extemporáneo
            if ($this->paid_date && $this->projected_date) {
                $projDate = $this->projected_date instanceof \Carbon\Carbon
                    ? $this->projected_date
                    : \Carbon\Carbon::parse($this->projected_date);

                $paidDate = $this->paid_date instanceof \Carbon\Carbon
                    ? $this->paid_date
                    : \Carbon\Carbon::parse($this->paid_date);

                $daysDiff = (int) $projDate->startOfDay()->diffInDays($paidDate->startOfDay(), false);

                if ($daysDiff <= 5) {
                    $this->status = 'on_time';
                } elseif ($daysDiff <= 10) {
                    $this->status = 'late';
                } else {
                    $this->status = 'defaulted';
                }
            } else {
                $this->status = 'paid';
            }
            $this->save();
            return;
        }

        // No pagada: calcular según fecha proyectada
        $projDate = $this->projected_date instanceof \Carbon\Carbon
            ? $this->projected_date
            : \Carbon\Carbon::parse($this->projected_date);

        $now = now()->startOfDay();
        $projDateStart = $projDate->copy()->startOfDay();
        $daysSinceProjected = (int) $projDateStart->diffInDays($now, false);

        if ($daysSinceProjected < 0) {
            $this->status = 'upcoming';
        } elseif ($daysSinceProjected <= 5) {
            $this->status = 'pending';
        } elseif ($daysSinceProjected <= 10) {
            $this->status = 'late';
        } else {
            $this->status = 'defaulted';
        }
        $this->save();
    }

    /**
     * Calcular el interés moratorio acumulado para esta cuota.
     * 10% mensual compuesto sobre el monto base.
     * Después de 5 días de gracia se aplica el primer 10%.
     * Cada 30 días adicionales se aplica otro 10% sobre el acumulado.
     */
    public function calculateInterest(): float
    {
        // Si ya está pagada, no hay interés pendiente
        if ($this->payment_id || in_array($this->status, ['paid', 'on_time'])) {
            return 0;
        }

        // Si se desactivó el interés (registro tardío en sistema), no se cobra
        if ($this->apply_interest === false) {
            return 0;
        }

        $projDate = $this->projected_date instanceof \Carbon\Carbon
            ? $this->projected_date
            : \Carbon\Carbon::parse($this->projected_date);

        $now = now()->startOfDay();
        $daysSinceProjected = (int) $projDate->copy()->startOfDay()->diffInDays($now, false);

        $lateDays = $daysSinceProjected - self::GRACE_PERIOD_DAYS;
        if ($lateDays <= 0) {
            return 0;
        }

        // Meses de interés: cada 30 días de retraso = 1 mes de interés compuesto
        // lateDays 1-30 → 1 mes, 31-60 → 2 meses, 61-90 → 3 meses, etc.
        $monthsOfInterest = (int) ceil($lateDays / 30);
        $totalWithInterest = (float) $this->amount * pow(1 + self::MONTHLY_INTEREST_RATE, $monthsOfInterest);

        return round($totalWithInterest - (float) $this->amount, 2);
    }

    /**
     * Accesor: monto total a pagar incluyendo interés moratorio compuesto.
     */
    public function getTotalWithInterestAttribute(): float
    {
        return round((float) $this->amount + $this->calculateInterest(), 2);
    }

    /**
     * Accesor: días de retraso después del período de gracia (0 si no hay).
     */
    public function getDaysLateAttribute(): int
    {
        if ($this->payment_id || in_array($this->status, ['paid', 'on_time'])) {
            return 0;
        }

        $projDate = $this->projected_date instanceof \Carbon\Carbon
            ? $this->projected_date
            : \Carbon\Carbon::parse($this->projected_date);

        $now = now()->startOfDay();
        $daysSinceProjected = (int) $projDate->copy()->startOfDay()->diffInDays($now, false);

        return max(0, $daysSinceProjected - self::GRACE_PERIOD_DAYS);
    }

    /**
     * Accesor: número de meses de interés acumulados (para el frontend).
     */
    public function getMonthsOfInterestAttribute(): int
    {
        $lateDays = $this->days_late;
        if ($lateDays <= 0) return 0;
        return (int) ceil($lateDays / 30);
    }
}
