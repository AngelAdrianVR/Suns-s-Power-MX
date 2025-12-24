<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBranchTrait; // trait para manejo de sucursales hecho por mi

class Document extends Model
{
    use HasFactory;
    use BelongsToBranchTrait; // Usar el trait para manejo de sucursales

    protected $fillable = [
        'name',
        'branch_id',
        'file_path',
        'mime_type',
        'documentable_id',
        'documentable_type',
        'category',
        'uploaded_by',
    ];

    // --- Relaciones ---

    // Relación polimórfica inversa (puede pertenecer a Client, ServiceOrder, Ticket, etc.)
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}