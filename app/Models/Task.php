<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'branch_id',
        'service_order_id',
        'description',
        'created_by',
        'start_date',
        'finish_date',
        'due_date',
        'status',
        'priority',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'finish_date' => 'datetime',
    ];

    // --- Relaciones ---

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_user')
                    ->withPivot('is_completed', 'assigned_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceOrder() // a qué orden de servicio pertenece la tarea
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
