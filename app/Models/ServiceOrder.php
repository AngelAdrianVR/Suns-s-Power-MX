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

        'notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    // --- Accessors ---

    /**
     * Helper para mostrar dirección de instalación completa
     */
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

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getProgressAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;
        
        $completedTasks = $this->tasks()->where('status', 'Completado')->count();
        
        return round(($completedTasks / $totalTasks) * 100);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}