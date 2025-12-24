<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'user_id',
        'type',
        'quantity',
        'stock_after',
        'reference_id',
        'reference_type',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relaciones
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relación polimórfica (Orden de Compra, Orden de Servicio, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Accessor para agrupar fácil en Vue (Ej: "Enero 2024")
    protected function groupDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at->translatedFormat('F Y'), // Requiere Carbon configurado en español
        );
    }
}