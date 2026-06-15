<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\BelongsToBranchTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceOrder extends Model implements HasMedia
{
    use HasFactory;
    use BelongsToBranchTrait; 
    use InteractsWithMedia;

    protected $fillable = [
        'client_id',
        'branch_id',
        'technician_id',
        'sales_rep_id',
        'status', 
        'start_date',
        'completion_date',
        'total_amount',
        'service_number',
        'rate_type',
        'inventory_reconciled',
        'system_type', // Interconectado, Autónomo, Multimodo, Respaldo, Bombeo u Otro.
        'voltage',           // <-- NUEVO
        'number_of_wires',   // <-- NUEVO
        'number_of_units',   // <-- NUEVO
        'unit_capacity',     // <-- NUEVO
        'total_capacity',    // <-- NUEVO
        'meter_number',
        
        // Installation Address Fields
        'installation_street',
        'installation_exterior_number',
        'installation_interior_number',
        'installation_neighborhood',
        'installation_municipality',
        'installation_state',
        'installation_zip_code',
        'installation_country',

        // Coordenadas
        'installation_lat',
        'installation_lng',

        // Propuesta comercial y reacondicionamientos
        'payment_method',
        'down_payment',
        'price_per_module', // <-- NUEVO costo de mantenimiento por modulo
        'extra_data',
        'requires_pre_installation',
        'pre_installation_details',
        'pre_installation_assigned_to',

        'notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
        'installation_lat' => 'decimal:8',
        'installation_lng' => 'decimal:8',
        'unit_capacity' => 'decimal:2',   // <-- NUEVO
        'total_capacity' => 'decimal:2',  // <-- NUEVO
        'down_payment' => 'decimal:2',
        'price_per_module' => 'decimal:2',
        'extra_data' => 'array',
        'requires_pre_installation' => 'boolean',
    ];

    protected function fullInstallationAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => implode(' ', array_filter([
                $this->installation_street,
                $this->installation_exterior_number ? "#{$this->installation_exterior_number}" : null,
                $this->installation_neighborhood ? ", Col. {$this->installation_neighborhood}" : null,
                $this->installation_municipality,
                $this->installation_state
            ]))
        );
    }

    // --- Relaciones ---
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'technician_id'); }
    public function salesRep(): BelongsTo { return $this->belongsTo(User::class, 'sales_rep_id'); }
    public function items(): HasMany { return $this->hasMany(ServiceOrderItem::class); }
    public function contract(): HasOne { return $this->hasOne(Contract::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function paymentInstallments(): HasMany { return $this->hasMany(PaymentInstallment::class); }
    public function tasks(): MorphMany { return $this->morphMany(Task::class, 'taskable'); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }

    public function conditionings(): HasMany { 
        return $this->hasMany(ServiceOrderConditioning::class); 
    }
    
    // NUEVA RELACIÓN PARA LAS EVIDENCIAS REQUERIDAS
    public function evidences(): HasMany {
        return $this->hasMany(ServiceOrderEvidence::class);
    }

    /**
     * Genera los registros de cuotas (payment_installments) basados en el plan de pago.
     * Se llama al crear o actualizar el método de pago.
     */
    public function generateInstallments(): void
    {
        $method = $this->payment_method;
        $totalAmount = (float) $this->total_amount;
        $startDate = $this->created_at ?? now();

        // Limpiar cuotas existentes que aún no estén pagadas
        $this->paymentInstallments()->whereNull('payment_id')->delete();

        if (!$method || $method === 'Personalizado' || in_array($this->status, ['Cotización', 'Cancelado'])) {
            return;
        }

        // El anticipo real está en la tabla payments (es la fuente de verdad).
        // El campo down_payment es solo metadata; NO se suma para evitar doble conteo.
        $anticipoTotal = (float) $this->payments()->where('notes', 'Anticipo')->sum('amount');
        $remainingAmount = $totalAmount - $anticipoTotal;

        $installments = [];

        if ($method === 'Contado') {
            $amount = max(0, $remainingAmount);
            if ($amount > 0) {
                $installments[] = [
                    'installment_number' => 1,
                    'label' => 'Pago único',
                    'projected_date' => $startDate->format('Y-m-d'),
                    'amount' => round($amount, 2),
                ];
            }
        } elseif (in_array($method, ['3 MSI', '6 MSI', '9 MSI', '12 MSI'])) {
            $months = (int) explode(' ', $method)[0];
            $monthlyAmount = $remainingAmount / $months;

            for ($i = 1; $i <= $months; $i++) {
                $installments[] = [
                    'installment_number' => $i,
                    'label' => "Mensualidad {$i} de {$months}",
                    'projected_date' => $startDate->copy()->addMonths($i)->format('Y-m-d'),
                    'amount' => round($monthlyAmount, 2),
                ];
            }
        }

        foreach ($installments as $data) {
            $this->paymentInstallments()->create($data);
        }
    }

    public function getProgressAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;
        $completedTasks = $this->tasks()->where('status', 'Completado')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Calcula la proyección de pagos basada en los registros de payment_installments.
     * Ahora lee de la base de datos en lugar de calcular sobre la marcha.
     */
    public function getPaymentProjection(): array
    {
        $method = $this->payment_method;
        $totalAmount = (float) $this->total_amount;

        if (in_array($this->status, ['Cotización', 'Cancelado'])) {
            return [
                'method' => $method,
                'has_installments' => false,
                'installments' => [],
                'unmatched_payments' => [],
            ];
        }

        // Cargar relaciones
        if (!$this->relationLoaded('paymentInstallments')) {
            $this->load('paymentInstallments.payment');
        }
        if (!$this->relationLoaded('payments')) {
            $this->load('payments');
        }

        // Para Personalizado: devolver instalments creados manualmente (si existen)
        if ($method === 'Personalizado') {
            $installments = $this->paymentInstallments->sortBy('installment_number')->map(function ($inst) {
                $inst->recalculateStatus();
                return $this->formatInstallment($inst);
            })->values()->toArray();

            return [
                'method' => $method,
                'has_installments' => count($installments) > 0,
                'installments' => $installments,
                'unmatched_payments' => [],
            ];
        }

        $downPayment = (float) ($this->down_payment ?? 0);

        $installments = $this->paymentInstallments->sortBy('installment_number')->map(function ($inst) {
            $inst->recalculateStatus();
            $inst->refresh();
            return $this->formatInstallment($inst);
        })->values()->toArray();

        // Pagos no emparejados con ninguna cuota
        $usedPaymentIds = $this->paymentInstallments->whereNotNull('payment_id')
            ->pluck('payment_id')->toArray();

        $unmatchedPayments = $this->payments
            ->filter(fn($p) => !in_array($p->id, $usedPaymentIds) && $p->notes !== 'Anticipo')
            ->values();

        return [
            'method' => $method,
            'has_installments' => true,
            'down_payment' => $downPayment,
            'total_amount' => $totalAmount,
            'installments' => $installments,
            'unmatched_payments' => $unmatchedPayments->map(fn($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'date' => $p->payment_date->format('Y-m-d'),
                'method' => $p->method,
                'reference' => $p->reference,
            ])->values()->toArray(),
        ];
    }

    /**
     * Formatea una cuota individual para la respuesta de la API.
     */
    private function formatInstallment($inst): array
    {
        $projDate = $inst->projected_date instanceof \Carbon\Carbon
            ? $inst->projected_date
            : \Carbon\Carbon::parse($inst->projected_date);

        $now = now()->startOfDay();
        $daysSinceProjected = (int) $projDate->copy()->startOfDay()->diffInDays($now, false);

        $result = [
            'id' => $inst->id,
            'installment' => $inst->installment_number,
            'label' => $inst->label,
            'projected_date' => $inst->projected_date->format('Y-m-d'),
            'amount' => (float) $inst->amount,
            'status' => $inst->status,
            'status_label' => $this->getStatusLabel($inst->status),
            'status_color' => $this->getStatusColor($inst->status),
            'days_since_projected' => max(0, $daysSinceProjected),
            'payment' => null,
        ];

        if ($inst->payment || $inst->payment_id) {
            $payment = $inst->payment;
            $payDate = $payment ? $payment->payment_date : $inst->paid_date;
            $payAmount = $payment ? (float) $payment->amount : (float) ($inst->paid_amount ?? 0);

            if ($payDate) {
                $payDateCarbon = $payDate instanceof \Carbon\Carbon
                    ? $payDate
                    : \Carbon\Carbon::parse($payDate);

                $daysDiff = (int) $projDate->startOfDay()->diffInDays($payDateCarbon->startOfDay(), false);

                $result['payment'] = [
                    'id' => $payment?->id,
                    'amount' => $payAmount,
                    'date' => $payDate instanceof \Carbon\Carbon ? $payDate->format('Y-m-d') : $payDate,
                    'method' => $payment?->method ?? '-',
                    'reference' => $payment?->reference ?? '-',
                    'days_diff' => $daysDiff,
                ];
            } else {
                $result['payment'] = [
                    'id' => $payment?->id,
                    'amount' => $payAmount,
                    'date' => $inst->paid_date?->format('Y-m-d'),
                    'method' => $payment?->method ?? '-',
                    'reference' => $payment?->reference ?? '-',
                    'days_diff' => 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Obtener etiqueta de texto para el estatus.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'on_time' => 'A tiempo',
            'late' => 'Pago extemporáneo',
            'defaulted' => 'Pago incumplido',
            'pending' => 'Pendiente',
            'upcoming' => 'Próximo',
            'paid' => 'Pagado',
            default => $status,
        };
    }

    /**
     * Obtener color para el estatus.
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'on_time', 'paid' => 'green',
            'late' => 'orange',
            'defaulted' => 'red',
            'pending' => 'gray',
            'upcoming' => 'blue',
            default => 'gray',
        };
    }
}