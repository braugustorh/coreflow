<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\DrillHoleSampleObserver;

#[ObservedBy([DrillHoleSampleObserver::class])]
class DrillHoleSample extends Model
{
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
}
