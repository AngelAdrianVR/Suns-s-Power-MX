<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\BelongsToBranchTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
 
class Client extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use BelongsToBranchTrait; 

    protected $fillable = [
        'name',
        'branch_id',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        
        // Address Fields
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'municipality',
        'state',
        'zip_code',
        'country',
        
        'coordinates',
        'email_secondary',
        'phone_secondary',
        'notes',
    ];

    // --- Accessors ---

    /**
     * Obtener la dirección completa concatenada para facilitar su visualización en listas.
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => implode(' ', array_filter([
                $this->street,
                $this->exterior_number ? "#{$this->exterior_number}" : null,
                $this->interior_number ? "Int. {$this->interior_number}" : null,
                $this->neighborhood ? ", Col. {$this->neighborhood}" : null,
                $this->zip_code ? "CP {$this->zip_code}" : null,
                $this->municipality,
                $this->state
            ]))
        );
    }

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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}