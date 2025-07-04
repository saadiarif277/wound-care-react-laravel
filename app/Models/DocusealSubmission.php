<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocusealSubmission extends Model
{
    protected $fillable = [
        'submission_id',
        'template_id',
        'status',
        'completed_at',
        'submitted_at',
        'signed_at',
        'submission_data',
    ];

    protected $casts = [
        'submission_data' => 'array',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'signed_at' => 'datetime',
    ];
}