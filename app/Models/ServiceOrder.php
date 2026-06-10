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
        'price_per_module',
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
    public function tasks(): MorphMany { return $this->morphMany(Task::class, 'taskable'); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }

    public function conditionings(): HasMany { 
        return $this->hasMany(ServiceOrderConditioning::class); 
    }
    
    // NUEVA RELACIÓN PARA LAS EVIDENCIAS REQUERIDAS
    public function evidences(): HasMany {
        return $this->hasMany(ServiceOrderEvidence::class);
    }

    public function getProgressAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;
        $completedTasks = $this->tasks()->where('status', 'Completado')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Calcula la proyección de pagos basada en el payment_method.
     * Retorna un array de cuotas proyectadas con su estatus respecto a pagos reales.
     */
    public function getPaymentProjection(): array
    {
        $method = $this->payment_method;
        $totalAmount = (float) $this->total_amount;
        $downPayment = (float) ($this->down_payment ?? 0);
        $startDate = $this->created_at ?? now();

        // Si no hay método de pago o es Cotización/Cancelado, no hay proyección
        if (!$method || in_array($this->status, ['Cotización', 'Cancelado'])) {
            return [];
        }

        $projections = [];

        if ($method === 'Contado') {
            $amount = $totalAmount - $downPayment;
            if ($amount > 0) {
                $projections[] = [
                    'installment' => 1,
                    'label' => 'Pago único',
                    'projected_date' => $startDate->format('Y-m-d'),
                    'amount' => round($amount, 2),
                ];
            }
        } elseif (in_array($method, ['3 MSI', '6 MSI', '9 MSI', '12 MSI'])) {
            $months = (int) explode(' ', $method)[0];
            $remainingAmount = $totalAmount - $downPayment;
            $monthlyAmount = $remainingAmount / $months;

            for ($i = 1; $i <= $months; $i++) {
                $projections[] = [
                    'installment' => $i,
                    'label' => "Mensualidad {$i} de {$months}",
                    'projected_date' => $startDate->copy()->addMonths($i)->format('Y-m-d'),
                    'amount' => round($monthlyAmount, 2),
                ];
            }
        } elseif ($method === 'Personalizado') {
            // Sin proyección fija; se devuelve vacío
            return [];
        }

        // Cargar pagos reales si no están cargados
        if (!$this->relationLoaded('payments')) {
            $this->load('payments');
        }

        $actualPayments = $this->payments->sortBy('payment_date')->values();
        $usedPaymentIds = [];

        // Mapa rápido: pagos con installment_number definido (prioridad máxima)
        $installmentPayments = $actualPayments->filter(fn($p) => $p->installment_number !== null)
            ->keyBy('installment_number');

        // Emparejar cada proyección con pagos reales
        // NOTA: Un mismo pago puede cubrir múltiples cuotas si el monto es mayor.
        $paymentCarryOver = null; // ['id', 'remaining_amount', 'date', 'method', 'reference']

        foreach ($projections as &$proj) {
            $projDate = \Carbon\Carbon::parse($proj['projected_date']);
            $instNum = $proj['installment'];
            $matchedPayment = null;
            $matchedAmount = 0;

            // 1. PRIORIDAD: emparejar por installment_number exacto
            if (isset($installmentPayments[$instNum]) && !in_array($installmentPayments[$instNum]->id, $usedPaymentIds)) {
                $p = $installmentPayments[$instNum];
                $usedPaymentIds[] = $p->id;
                $matchedPayment = [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'date' => $p->payment_date->format('Y-m-d'),
                    'method' => $p->method,
                    'reference' => $p->reference,
                ];
            }

            // 2. Intentar usar el carry-over de un pago grande anterior
            if ($paymentCarryOver && $paymentCarryOver['remaining_amount'] >= $proj['amount']) {
                $matchedPayment = [
                    'id' => $paymentCarryOver['id'],
                    'amount' => $proj['amount'],
                    'date' => $paymentCarryOver['date'],
                    'method' => $paymentCarryOver['method'],
                    'reference' => $paymentCarryOver['reference'],
                ];
                $paymentCarryOver['remaining_amount'] -= $proj['amount'];
                if ($paymentCarryOver['remaining_amount'] < 0.01) {
                    $paymentCarryOver = null;
                }
            }

            // 2. Si no hay carry-over, buscar en pagos reales (ventana: 15 días antes a +ilimitado)
            if (!$matchedPayment) {
                $bestMatch = null;
                $bestIdx = null;

                foreach ($actualPayments as $idx => $payment) {
                    if (in_array($payment->id, $usedPaymentIds)) continue;
                    $payDate = \Carbon\Carbon::parse($payment->payment_date);
                    // Permitir hasta 15 días antes de la fecha proyectada
                    if ($payDate->gte($projDate->copy()->subDays(15))) {
                        if ($bestMatch === null || $payDate->lt(\Carbon\Carbon::parse($bestMatch->payment_date))) {
                            $bestMatch = $payment;
                            $bestIdx = $idx;
                        }
                    }
                }

                if ($bestMatch) {
                    $usedPaymentIds[] = $bestMatch->id;
                    $matchAmount = (float) $bestMatch->amount;

                    // Si el pago cubre más que esta cuota, guardar carry-over
                    if ($matchAmount > $proj['amount'] + 0.01) {
                        $paymentCarryOver = [
                            'id' => $bestMatch->id,
                            'remaining_amount' => $matchAmount - $proj['amount'],
                            'date' => $bestMatch->payment_date->format('Y-m-d'),
                            'method' => $bestMatch->method,
                            'reference' => $bestMatch->reference,
                        ];
                        $matchedAmount = $proj['amount'];
                    } else {
                        $matchedAmount = $matchAmount;
                    }

                    $matchedPayment = [
                        'id' => $bestMatch->id,
                        'amount' => $matchedAmount,
                        'date' => $bestMatch->payment_date->format('Y-m-d'),
                        'method' => $bestMatch->method,
                        'reference' => $bestMatch->reference,
                    ];
                }
            }

            if ($matchedPayment) {
                $payDate = \Carbon\Carbon::parse($matchedPayment['date']);
                $daysDiff = (int) $projDate->startOfDay()->diffInDays($payDate->startOfDay(), false);

                // Clasificación: positivo = días después de la fecha proyectada
                if ($daysDiff <= 5) {
                    $proj['status'] = 'on_time';
                    $proj['status_label'] = 'A tiempo';
                    $proj['status_color'] = 'green';
                } elseif ($daysDiff <= 10) {
                    $proj['status'] = 'late';
                    $proj['status_label'] = 'Pago extemporáneo';
                    $proj['status_color'] = 'orange';
                } else {
                    $proj['status'] = 'defaulted';
                    $proj['status_label'] = 'Pago incumplido';
                    $proj['status_color'] = 'red';
                }

                $proj['payment'] = [
                    'id' => $matchedPayment['id'],
                    'amount' => $matchedPayment['amount'],
                    'date' => $matchedPayment['date'],
                    'days_diff' => $daysDiff,
                    'method' => $matchedPayment['method'],
                    'reference' => $matchedPayment['reference'],
                ];
            } else {
                // Sin pago asociado: clasificar según qué tan lejos está la fecha proyectada
                // IMPORTANTE: $projDate->diffInDays(now(), false) da NEGATIVO para fechas futuras,
                // POSITIVO para fechas pasadas.
                $now = now()->startOfDay();
                $projDateStart = $projDate->copy()->startOfDay();
                $daysSinceProjected = (int) $projDateStart->diffInDays($now, false);

                // Si la fecha proyectada es FUTURA (negativo), nunca marcar como incumplido
                if ($daysSinceProjected < 0) {
                    $proj['status'] = 'upcoming';
                    $proj['status_label'] = 'Próximo';
                    $proj['status_color'] = 'blue';
                } elseif ($daysSinceProjected <= 5) {
                    $proj['status'] = 'pending';
                    $proj['status_label'] = 'Pendiente';
                    $proj['status_color'] = 'gray';
                } elseif ($daysSinceProjected <= 10) {
                    $proj['status'] = 'late';
                    $proj['status_label'] = 'Pago extemporáneo';
                    $proj['status_color'] = 'orange';
                } else {
                    $proj['status'] = 'defaulted';
                    $proj['status_label'] = 'Pago incumplido';
                    $proj['status_color'] = 'red';
                }

                $proj['payment'] = null;
                $proj['days_since_projected'] = max(0, $daysSinceProjected);
            }
        }

        // Pagos no emparejados (excedentes o adelantados)
        $unmatchedPayments = $actualPayments->filter(fn($p) => !in_array($p->id, $usedPaymentIds))->values();

        return [
            'method' => $method,
            'down_payment' => $downPayment,
            'total_amount' => $totalAmount,
            'installments' => $projections,
            'unmatched_payments' => $unmatchedPayments->map(fn($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'date' => $p->payment_date->format('Y-m-d'),
                'method' => $p->method,
                'reference' => $p->reference,
            ])->values()->toArray(),
        ];
    }
}