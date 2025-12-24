<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\BelongsToBranchTrait; // trait para manejo de sucursales hecho por mi

class Client extends Model 
{
    use HasFactory;
    use BelongsToBranchTrait; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'name',
        'branch_id',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        'address',
        'coordinates',
        'notes',
    ];

    // --- Relaciones ---

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Relación polimórfica con Documentos (Expediente del cliente)
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
