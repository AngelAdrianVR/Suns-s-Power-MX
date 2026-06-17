<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentInstallment extends Model
{
    protected $fillable = [
        'service_order_id',
        'installment_number',
        'label',
        'projected_date',
        'amount',
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
}
