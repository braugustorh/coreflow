<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DrillHoleGapValidation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'barreno_id',
        'from_depth',
        'to_depth',
        'reason',
        'validated_by',
        'validated_at',
        'notes',
    ];

    protected $casts = [
        'from_depth'   => 'decimal:2',
        'to_depth'     => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function barreno(): BelongsTo
    {
        return $this->belongsTo(DrillHole::class, 'barreno_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
