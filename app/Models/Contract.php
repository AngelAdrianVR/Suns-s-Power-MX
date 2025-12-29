<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBranchTrait; // trait para manejo de sucursales hecho por mi

class Contract extends Model
{
    use HasFactory;
    use BelongsToBranchTrait; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'service_order_id',
        'generated_at',
        'content_json',
        'signed_url',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'content_json' => 'array', // Casteo automático a array/JSON
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}