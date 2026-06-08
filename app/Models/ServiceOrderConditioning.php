<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceOrderConditioning extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'service_order_id',
        'category', // Instalación Eléctrica / Área de Instalación
        'task', // Tarea específica a realizar
        'user_id', // Usuario asignado a ejecutar esta tarea
        'status', // Pendiente, En Proceso, Terminado
        'notes', 
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}