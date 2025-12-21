<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Traits\BelongsToBranch; // trait para manejo de sucursales hecho por mi

class ServiceOrder extends Model 
{
    use HasFactory;
    use BelongsToBranch; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'client_id',
        'branch_id',
        'technician_id',
        'sales_rep_id',
        'status',
        'start_date',
        'completion_date',
        'total_amount',
        'installation_address',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    // --- Relaciones ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Documentos asociados (Evidencias de instalación, fotos)
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}