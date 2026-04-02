<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceOrderEvidence extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    // Agrega esta línea para forzar el nombre de la tabla con 's'
    protected $table = 'service_order_evidences';
    
    protected $fillable = [
        'service_order_id',
        'title',
        'description',
        'allows_multiple' // <-- NUEVO: Para saber si esta evidencia en particular acepta múltiples fotos
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}