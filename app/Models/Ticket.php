<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Traits\BelongsToBranch; // trait para manejo de sucursales hecho por mi

class Ticket extends Model
{
    use HasFactory;
    use BelongsToBranch; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'client_id',
        'branch_id',
        'related_service_order_id',
        'title',
        'description',
        'status',
        'priority',
        'resolution_notes',
        'converted_to_order_id',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'related_service_order_id');
    }
    
    // Si el ticket se convirtió en una nueva orden
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'converted_to_order_id');
    }

    // Evidencias del fallo (fotos, videos)
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
