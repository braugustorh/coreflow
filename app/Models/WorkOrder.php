<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class WorkOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'samples_quantity'       => 'integer',
        'received_samples_count' => 'integer',
        'dispatch_date'          => 'date',
        'reception_date'         => 'date',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function drillHoleSamples(): HasMany
    {
        return $this->hasMany(DrillHoleSample::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Work Orders que tienen al menos una muestra asignada.
     */
    public function scopeWithSamples(Builder $query): Builder
    {
        return $query->whereHas('drillHoleSamples');
    }

    // ─── Accessors Calculados para Summary Samples ───────────────────────────

    /**
     * Fecha de Envío: toma dispatch_date de la WO o, si no está definido,
     * el sent_date registrado en las muestras del Monitor de Muestreo.
     */
    public function getDispatchDateAttribute($value): ?Carbon
    {
        if ($value) {
            return Carbon::parse($value);
        }

        $sampleSent = $this->drillHoleSamples()->whereNotNull('sent_date')->value('sent_date');

        return $sampleSent ? Carbon::parse($sampleSent) : null;
    }

    /**
     * TURNAROUND_TIME: Tiempo transcurrido (en días) desde que se mandó al laboratorio.
     * - Si ya se cuenta con fecha de recepción: días entre el envío y la recepción.
     * - Si aún no se recibe: días transcurridos desde la fecha de envío hasta el día de hoy.
     * - Si no tiene fecha de envío: null (se muestra 'N/D').
     */
    public function getTurnaroundTimeAttribute(): ?int
    {
        $dispatch = $this->dispatch_date;

        if (!$dispatch) {
            return null;
        }

        $start = Carbon::parse($dispatch)->startOfDay();
        $end   = $this->reception_date
            ? Carbon::parse($this->reception_date)->startOfDay()
            : Carbon::now()->startOfDay();

        return max(0, (int) $start->diffInDays($end, false));
    }

    /**
     * Conteo de muestras originales (únicamente tipo 'O' o sin control).
     */
    public function getOriginalSamplesCountAttribute(): int
    {
        return $this->drillHoleSamples()
            ->where(function ($q) {
                $q->whereNull('sample_type')
                  ->orWhere('sample_type', 'O')
                  ->orWhere('sample_type', '!=', 'CONTROL');
            })
            ->count();
    }

    /**
     * Conteo de muestras QC (todas las de control / standards).
     */
    public function getQcSamplesCountAttribute(): int
    {
        return $this->drillHoleSamples()
            ->where('sample_type', 'CONTROL')
            ->count();
    }

    /**
     * Conteo de PULP Samples (muestras de control de tipo Blank/Blanco).
     */
    public function getPulpSamplesCountAttribute(): int
    {
        return $this->drillHoleSamples()
            ->where('sample_type', 'CONTROL')
            ->where(function ($q) {
                $q->where('control_type', 'like', '%BLANK%')
                  ->orWhere('control_type', 'like', '%BLK%')
                  ->orWhere('control_type', 'like', '%PULP%')
                  ->orWhere('sample_number', 'like', '%BLK%')
                  ->orWhere('sample_number', 'like', '%BLANK%');
            })
            ->count();
    }

    /**
     * Nombres únicos de barrenos asociados a esta WO.
     */
    public function getBarrenosListAttribute(): string
    {
        $names = $this->drillHoleSamples()
            ->whereHas('barreno')
            ->with('barreno')
            ->get()
            ->pluck('barreno.nombre_barreno')
            ->unique()
            ->filter()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'N/A';
    }

    /**
     * Nombres de proyectos asociados a las muestras de esta WO.
     */
    public function getProyectosListAttribute(): string
    {
        $projects = $this->drillHoleSamples()
            ->whereHas('proyecto')
            ->with('proyecto')
            ->get()
            ->pluck('proyecto.nombre')
            ->unique()
            ->filter()
            ->values();

        return $projects->isNotEmpty() ? $projects->implode(', ') : 'N/A';
    }

    /**
     * Retorna el valor predominante de responsible_supervisor en las muestras de esta WO.
     */
    public function getPredominantResponsibleSupervisorAttribute(): ?string
    {
        return $this->drillHoleSamples()
            ->select('responsible_supervisor')
            ->whereNotNull('responsible_supervisor')
            ->groupBy('responsible_supervisor')
            ->orderByRaw('COUNT(*) DESC')
            ->value('responsible_supervisor');
    }
}
