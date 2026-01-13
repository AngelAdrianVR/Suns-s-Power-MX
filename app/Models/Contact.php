<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'branch_id',
        'contactable_id',
        'contactable_type',
        'name',
        'job_title',
        'email',
        'phone',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Obtener el modelo padre propietario del contacto (Supplier, Client, etc).
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relación con la sucursal (Multi-tenant).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}