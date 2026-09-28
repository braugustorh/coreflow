<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    protected $guarded = [];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function drillHoleSamples(): HasMany
    {
        return $this->hasMany(DrillHoleSample::class);
    }

    public function drillHoles(): HasMany
    {
        return $this->hasMany(DrillHole::class);
    }

    public function historicalSamples(): HasMany
    {
        return $this->hasMany(HistoricalSample::class);
    }
}
