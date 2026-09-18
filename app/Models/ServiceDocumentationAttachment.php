<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDocumentationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_id',
        'step_id',
        'source',
        'media_id',
        'file_name',
        'mime_type',
        'file_path',
        'url',
        'created_by',
    ];

    // upload | client | order | product
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_CLIENT = 'client';
    public const SOURCE_ORDER = 'order';
    public const SOURCE_PRODUCT = 'product';

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ServiceDocumentationStep::class, 'step_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
