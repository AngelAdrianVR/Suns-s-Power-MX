<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToBranchTrait;

class ServiceDocumentationStep extends Model
{
    use HasFactory;
    use BelongsToBranchTrait;

    protected $fillable = [
        'branch_id',
        'title',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceDocumentationAttachment::class, 'step_id');
    }
}
