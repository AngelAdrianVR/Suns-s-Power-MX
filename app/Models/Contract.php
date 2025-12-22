<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

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