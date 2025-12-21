<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToBranch; // Trait Multi-sucursal

class MaintenanceSchedule extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'client_id',
        'service_order_id',
        'technician_id',
        'frequency', // Enum: Mensual, Trimestral...
        'scheduled_date',
        'status',    // Enum: Programado, Realizado...
        'notes',
        'branch_id', // Importante para el trait
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}