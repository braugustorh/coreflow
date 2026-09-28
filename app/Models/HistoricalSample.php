<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalSample extends Model
{
    protected $fillable = [
        'sample_number',
        'proyecto_id',
        'barreno_name',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * Scope para filtrar por proyecto o muestras sin proyecto asignado (globales).
     */
    public function scopeForProjectOrGlobal(Builder $query, ?int $proyectoId): Builder
    {
        if (!$proyectoId) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($proyectoId) {
            $q->where('proyecto_id', $proyectoId)
              ->orWhereNull('proyecto_id');
        });
    }
}
