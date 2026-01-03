<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'branch_id',
        'client_id',
        'related_service_order_id',
        'title',
        'description',
        'priority',
        'status',
        'resolution_notes',
    ];

    // ==========================================
    // Relaciones Principales
    // ==========================================

    /**
     * Sucursal a la que pertenece el ticket.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Cliente que reporta el ticket.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Orden de servicio relacionada (Origen: si el ticket proviene de una orden).
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'related_service_order_id');
    }

    /**
     * Orden de servicio generada a partir de este ticket (Destino: si el ticket se convirtió).
     * Asume que en la tabla 'service_orders' existe un campo 'ticket_id' opcional.
     */
    public function convertedOrder(): HasOne
    {
        return $this->hasOne(ServiceOrder::class, 'ticket_id');
    }

    /**
     * Comentarios del ticket (Timeline/Respuestas).
     * Se usa 'morphMany' asumiendo que tu tabla de comentarios tiene 
     * 'commentable_id' y 'commentable_type' para usarse en Tickets, Tareas, etc.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}