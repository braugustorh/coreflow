<?php

namespace App\Models;

use App\Observers\DrillHoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([DrillHoleObserver::class])]
class DrillHole extends Model
{
    protected $fillable = [
        'nombre_barreno',
        'is_child',
        'parent_id',
        'target',
        'sede_id',
        'proyecto_id',
        // Atributos Planeados
        'planned_easting',
        'planned_northing',
        'planned_elevation',
        'planned_dip',
        'planned_azimuth',
        // Atributos Instalados (Medidos)
        'easting',
        'northing',
        'elevation',
        'dip',
        'azimuth',
        // Reporte y Metadatos
        'survey_pdf_path',
        'planilla',
        'survey_responsible',
        'survey_date',
        // Especificaciones Técnicas
        'start_depth',
        'max_depth',
        'drilling_type',
        'core_size',
        'purpose',
        'is_historical',
        'status',
    ];

    protected $casts = [
        'is_child'          => 'boolean',
        'is_historical'     => 'boolean',
        'planned_easting'   => 'decimal:3',
        'planned_northing'  => 'decimal:3',
        'planned_elevation' => 'decimal:2',
        'planned_dip'       => 'decimal:2',
        'planned_azimuth'   => 'decimal:2',
        'easting'           => 'decimal:3',
        'northing'          => 'decimal:3',
        'elevation'         => 'decimal:2',
        'dip'               => 'decimal:2',
        'azimuth'           => 'decimal:2',
        'start_depth'       => 'decimal:2',
        'max_depth'         => 'decimal:2',
        'survey_date'       => 'date',
    ];

    public function scopeOperational(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_historical', false);
    }

    public function scopeHistorical(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_historical', true);
    }

    /**
     * Determina si el barreno cuenta con atributos instalados capturados.
     */
    public function hasInstalledAttributes(): bool
    {
        return !is_null($this->easting) && !is_null($this->northing) && !is_null($this->elevation);
    }

    /**
     * Determina si el barreno tiene un reporte PDF adjunto.
     */
    public function hasSurveyPdf(): bool
    {
        return !empty($this->survey_pdf_path);
    }

    /**
     * Determina si el barreno es un barreno hijo (desvío / wedge / continuación).
     */
    public function isChild(): bool
    {
        return (bool) ($this->is_child || $this->parent_id !== null);
    }

    /**
     * Determina si el barreno es madre de otros barrenos hijos.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    // ─── Relaciones BelongsTo ────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DrillHole::class, 'parent_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    // ─── Relaciones HasMany ──────────────────────────────────────────────────

    public function children(): HasMany
    {
        return $this->hasMany(DrillHole::class, 'parent_id');
    }

    public function drillHoleSamples(): HasMany
    {
        return $this->hasMany(DrillHoleSample::class, 'barreno_id');
    }

    public function gapValidations(): HasMany
    {
        return $this->hasMany(DrillHoleGapValidation::class, 'barreno_id');
    }

    /**
     * Obtiene el nombre del usuario (geólogo/capturista) que cargó las muestras en borrador.
     */
    public function getDraftUploaderNameAttribute(): string
    {
        $firstDraft = $this->drillHoleSamples()
            ->where('status', 'draft')
            ->where('capture_source', 'import')
            ->with('user')
            ->first();

        return $firstDraft?->user?->name ?? 'Sin asignar';
    }

    /**
     * Obtiene la fecha más reciente de carga de muestras en borrador.
     */
    public function getDraftSampledAtAttribute()
    {
        $firstDraft = $this->drillHoleSamples()
            ->where('status', 'draft')
            ->where('capture_source', 'import')
            ->latest('created_at')
            ->first();

        return $firstDraft?->created_at ?? $firstDraft?->sampled_at;
    }
}
