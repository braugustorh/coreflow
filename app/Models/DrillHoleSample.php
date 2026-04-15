<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrillHoleSample extends Model
{
    protected $fillable = [
        'user_id',
        'bhid',
        'from',
        'to',
        'drilled_length',
        'sample_length',
        'sample_number',
        'sample_type',
        'control_type',
        'wght',
        'comments',
        'project',
        'core_size',
        'work_order',
        'costal',
        'status',
        'errors',
    ];

    protected $casts = [
        'errors' => 'array',
        'from' => 'decimal:3',
        'to' => 'decimal:3',
        'drilled_length' => 'decimal:3',
        'sample_length' => 'decimal:3',
        'wght' => 'decimal:3',
    ];
}
