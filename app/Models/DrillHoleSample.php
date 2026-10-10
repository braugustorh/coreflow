<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\DrillHoleSampleObserver;

#[ObservedBy([DrillHoleSampleObserver::class])]
class DrillHoleSample extends Model
{
    public const QC_PHOTOS_REQUIRED = 2;
    protected $guarded = [];

    protected $casts = [
        'errors'         => 'array',
        'from_depth'     => 'decimal:2',
        'to_depth'       => 'decimal:2',
        'length'         => 'decimal:2',
        'sample_length'  => 'decimal:2',
        'weight'         => 'decimal:2',
        'sampled_at'     => 'datetime',
        'sent_date'      => 'date',
        'rush'           => 'boolean',
        'is_archived'    => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function barreno(): BelongsTo
    {
        return $this->belongsTo(DrillHole::class, 'barreno_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function standardSample(): BelongsTo
    {
        return $this->belongsTo(StandardSample::class);
    }

    public function duplicateSample(): BelongsTo
    {
        return $this->belongsTo(DrillHoleSample::class, 'duplicate_sample_id');
    }

    public function qcPhotos(): HasMany
    {
        return $this->hasMany(QcSamplePhoto::class);
    }

    public function requiresQcPhotos(): bool
    {
        if (strtoupper(trim((string) $this->sample_type)) !== 'CONTROL') {
            return false;
        }

        if ($this->duplicate_sample_id) {
            return false;
        }

        return !str_contains(strtoupper((string) $this->control_type), 'DUP');
    }

    public function hasCompleteQcPhotos(): bool
    {
        $count = $this->qc_photos_count ?? $this->qcPhotos()->count();

        return $count >= self::QC_PHOTOS_REQUIRED;
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Solo registros activos (no archivados) que pertenecen a una Work Order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false)
                     ->whereNotNull('work_order_id');
    }

    /**
     * Solo registros archivados.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    /**
     * Solo registros acreditados / oficiales.
     */
    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('status', 'official');
    }

    /**
     * Solo registros de una Work Order específica.
     */
    public function scopeForWorkOrder(Builder $query, int $workOrderId): Builder
    {
        return $query->where('work_order_id', $workOrderId);
    }

    /**
     * Solo muestras en borrador de importación CSV.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft')->where('capture_source', 'import');
    }

    /**
     * Solo registros de un barreno específico.
     */
    public function scopeForBarreno(Builder $query, int $barrenoId): Builder
    {
        return $query->where('barreno_id', $barrenoId);
    }

    /**
     * Muestras QC que requieren evidencia fotográfica (estándares y blancos, excluyendo duplicados).
     */
    public function scopeRequiringQcPhotos(Builder $query): Builder
    {
        return $query->whereRaw("UPPER(TRIM(sample_type)) = 'CONTROL'")
            ->whereNull('duplicate_sample_id')
            ->where(function ($s) {
                $s->whereNull('control_type')
                    ->orWhereRaw("UPPER(control_type) NOT LIKE '%DUP%'");
            });
    }

    /**
     * Muestras QC que tienen fotos pendientes (menos de 2 fotos).
     */
    public function scopeMissingQcPhotos(Builder $query): Builder
    {
        return $query->requiringQcPhotos()->has('qcPhotos', '<', self::QC_PHOTOS_REQUIRED);
    }

    /**
     * Determina si la muestra o su Work Order ya fue enviada al laboratorio.
     */
    public function isSent(): bool
    {
        if ($this->is_archived) {
            return true;
        }

        if ($this->relationLoaded('workOrder') && $this->workOrder) {
            return (bool) $this->workOrder->sent_to_lab;
        }

        if ($this->work_order_id) {
            return WorkOrder::where('id', $this->work_order_id)
                ->where('sent_to_lab', true)
                ->exists();
        }

        return false;
    }
}
