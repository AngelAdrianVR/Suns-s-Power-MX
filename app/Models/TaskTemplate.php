<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'system_type',
        'title',
        'description',
        'priority',
        'start_days',      // <-- NUEVO CAMPO: Días para iniciar
        'duration_days',   // <-- NUEVO CAMPO: Duración en días
        'is_recurring',
        'recurring_interval',
        'recurring_unit',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Relación para saber a quiénes se les asignará la tarea por defecto
    public function users()
    {
        return $this->belongsToMany(User::class, 'task_template_user');
    }

    // Relación para saber qué evidencias requiere esta tarea
    public function evidenceTemplates()
    {
        return $this->belongsToMany(EvidenceTemplate::class, 'evidence_template_task_template');
    }
}