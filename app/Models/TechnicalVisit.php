<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TechnicalVisit extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'requires_long_ladder' => 'boolean',
        'requires_pre_installation' => 'boolean',
        'backup_devices' => 'array', // Castea el JSON a un array nativo de PHP
        'module_capacity' => 'decimal:2',
        'budget' => 'decimal:2',
        'gross_installed_capacity' => 'decimal:2',
        'estimated_daily_generation' => 'decimal:2',
        'estimated_monthly_generation' => 'decimal:2',
        'estimated_monthly_saving' => 'decimal:2',
        'battery_capacity' => 'decimal:2',
        'voltage' => 'decimal:2',
    ];

    /**
     * Registra las colecciones de medios para evidencias del checklist.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('facade_photo')->singleFile();
        $this->addMediaCollection('meter_photo')->singleFile();
        $this->addMediaCollection('meter_prep_photo')->singleFile();
        $this->addMediaCollection('main_panel_photo')->singleFile();
        $this->addMediaCollection('secondary_panel_photo')->singleFile();
        $this->addMediaCollection('additional_evidences');
    }

    /**
     * Obtiene el nombre completo del prospecto o cliente.
     */
    public function getFullNameAttribute()
    {
        if ($this->client_id && $this->client) {
            return $this->client->name;
        }

        if ($this->business_name) {
            return $this->business_name;
        }

        return trim("{$this->first_name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    /**
     * Obtiene la dirección completa concatenada.
     */
    public function getFullAddressAttribute()
    {
        return trim("{$this->road_type} {$this->street} {$this->exterior_number} " . 
               ($this->interior_number ? "Int {$this->interior_number}, " : ", ") . 
               "{$this->neighborhood}, {$this->municipality}, {$this->state}");
    }

    // --- Relaciones ---

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}