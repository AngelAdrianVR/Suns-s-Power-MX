<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Abono registrado por el cliente desde el portal.
 * Se valida manualmente desde el ERP (aprobación/rechazo).
 */
class PortalPayment extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const STATUS_IN_REVIEW = 'En revisión';
    public const STATUS_COMPLETED = 'Completado';
    public const STATUS_REJECTED = 'Rechazado';

    protected $table = 'portal_payments';

    protected $fillable = [
        'branch_id',
        'client_id',
        'service_order_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'notes',
        'status',
        'validated_by',
        'validated_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts')->singleFile();
    }
}
