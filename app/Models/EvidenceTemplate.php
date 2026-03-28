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
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}