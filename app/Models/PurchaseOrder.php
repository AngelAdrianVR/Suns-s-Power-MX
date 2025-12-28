<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToBranchTrait; // trait para manejo de sucursales hecho por mi

class PurchaseOrder extends Model
{
    use HasFactory;
    use BelongsToBranchTrait; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'supplier_id',
        'branch_id',
        'requested_by',
        'status',
        'currency',
        'total_cost',
        'expected_date',
        'received_date', // fecha de recepción
        'notes',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'received_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
