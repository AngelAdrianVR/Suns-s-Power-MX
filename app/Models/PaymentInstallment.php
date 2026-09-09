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

    /** Días comerciales considerados como 1 mes para el cálculo de interés */
    public const DAYS_PER_MONTH = 30;

    /**
     * Tasa diaria equivalente (compuesto diario tipo bancario).
     * Se obtiene capitalizando la tasa mensual del 10% en 30 días:
     *   (1 + 0.10)^(1/30) - 1 ≈ 0.3184% diario
     */
    public static function dailyInterestRate(): float
    {
        return pow(1 + self::MONTHLY_INTEREST_RATE, 1 / self::DAYS_PER_MONTH) - 1;
    }

    /**
     * Calcula el interés compuesto diario para un monto y un número de días de retraso
     * después del periodo de gracia (tipo bancario).
     */
    public static function interestForLateDays(float $amount, int $lateDays): float
    {
        if ($lateDays <= 0 || $amount <= 0) {
            return 0.0;
        }

        $daily = self::dailyInterestRate();
        $total = (float) $amount * pow(1 + $daily, $lateDays);

        return round($total - (float) $amount, 2);
    }

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
     * 10% mensual con capitalización DIARIA tipo bancario.
     *
     * Después de los 5 días de gracia, el interés comienza a crecer TODOS los días
     * de forma proporcional y compuesta (cada día se capitaliza sobre el saldo
     * del día anterior usando la tasa diaria equivalente a 10% mensual).
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

        // Días de retraso DESPUÉS del periodo de gracia
        $lateDays = $daysSinceProjected - self::GRACE_PERIOD_DAYS;
        if ($lateDays <= 0) {
            return 0;
        }

        return self::interestForLateDays((float) $this->amount, $lateDays);
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
     * Es exactamente el número de días sobre los que se capitaliza el interés.
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
     * Accesor: días de interés devengados (igual que days_late; se expone
     * con este nombre para el frontend).
     */
    public function getInterestDaysAttribute(): int
    {
        return $this->days_late;
    }

    /**
     * Accesor: número de meses de interés acumulados (para el frontend).
     * @deprecated Con la capitalización diaria se usa interest_days/days_late.
     */
    public function getMonthsOfInterestAttribute(): int
    {
        return (int) ceil($this->days_late / self::DAYS_PER_MONTH);
    }
}
