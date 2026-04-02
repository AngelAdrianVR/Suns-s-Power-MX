<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'system_type',
        'title',
        'description',
        'order',            // NUEVO: Para guardar el orden de la evidencia
        'allows_multiple'   // NUEVO: Para saber si permite múltiples archivos
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}