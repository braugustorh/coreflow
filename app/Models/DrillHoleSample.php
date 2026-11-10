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

    /** Texto estándar (sin acentos) para el comentario automático de baja recuperación. */
    public const LOW_RECOVERY_COMMENT = 'baja recuperacion';

    /** Tolerancia numérica usada en comparaciones de recuperación vs longitud. */
    public const LOW_RECOVERY_TOLERANCE = 0.001;

    protected $guarded = [];

    protected $casts = [
        'errors'         => 'array',
        'from_depth'     => 'decimal:2',
        'to_depth'       => 'decimal:2',
        'length'         => 'decimal:2',
        'sample_length'  => 'decimal:2',
        'weight'         => 'decimal:3',
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

    public function geologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geologist_id');
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

    // ─── Clasificación de Muestras y Elegibilidad Core Size ─────────────────

    /**
     * Determina si la muestra es original (no control).
     */
    public function isOriginal(): bool
    {
        $type = strtoupper(trim((string) $this->sample_type));
        return ($type === 'O' || $type === 'ORIGINAL' || ($type === '' && empty($this->control_type)));
    }

    /**
     * Determina si la muestra es un duplicado de control.
     */
    public function isDuplicate(): bool
    {
        if ($this->duplicate_sample_id !== null) {
            return true;
        }

        $ctrl = strtoupper(trim((string) $this->control_type));
        return str_contains($ctrl, 'DUP');
    }

    /**
     * Determina si la muestra es un Blanco.
     */
    public function isBlank(): bool
    {
        $ctrl = strtoupper(trim((string) $this->control_type));
        return str_contains($ctrl, 'BLANK')
            || str_contains($ctrl, 'BLK')
            || str_contains($ctrl, 'BLANCO')
            || $ctrl === 'BCO';
    }

    /**
     * Determina si la muestra es un Estándar.
     */
    public function isStandard(): bool
    {
        if ($this->standard_sample_id !== null) {
            return true;
        }

        $ctrl = strtoupper(trim((string) $this->control_type));
        return str_contains($ctrl, 'STAND')
            || str_starts_with($ctrl, 'STD-')
            || $ctrl === 'ESTANDAR'
            || $ctrl === 'ESTÁNDAR';
    }

    /**
     * Determina si la muestra es elegible para Core Size (Solo Originales y Duplicados).
     */
    public function isEligibleForCoreSize(): bool
    {
        if ($this->isBlank() || $this->isStandard()) {
            return false;
        }

        return $this->isOriginal() || $this->isDuplicate();
    }

    /**
     * Determina si la muestra NO debe tener Core Size (Blancos y Estándares).
     */
    public function isIneligibleForCoreSize(): bool
    {
        return !$this->isEligibleForCoreSize();
    }

    // ─── Baja Recuperación (Sample Length < Length) ─────────────────────────

    /**
     * Determina si una combinación longitud/recuperación corresponde a "baja recuperación".
     * Se excluye explícitamente sample_length <= 0 (cubierto por otra validación: "debe ser > 0").
     */
    public static function isLowRecovery(?float $length, ?float $sampleLength): bool
    {
        if ($length === null || $sampleLength === null) {
            return false;
        }
        if ($sampleLength <= 0) {
            return false;
        }

        return $sampleLength < ($length - self::LOW_RECOVERY_TOLERANCE);
    }

    /**
     * Evalúa la baja recuperación usando los valores actuales del registro.
     */
    public function hasLowRecovery(): bool
    {
        if (strtoupper(trim((string) $this->sample_type)) === 'CONTROL') {
            return false;
        }

        return static::isLowRecovery(
            $this->length !== null ? (float) $this->length : null,
            $this->sample_length !== null ? (float) $this->sample_length : null
        );
    }

    /**
     * True si la muestra tiene baja recuperación y el campo de comentarios está vacío.
     */
    public function needsLowRecoveryComment(): bool
    {
        return $this->hasLowRecovery() && trim((string) $this->comentarios) === '';
    }

    /**
     * Scope: muestras originales con baja recuperación (sample_length < length) y comentarios vacíos.
     */
    public function scopeLowRecoveryMissingComment(Builder $query): Builder
    {
        return $query->whereRaw("UPPER(TRIM(sample_type)) != 'CONTROL'")
            ->whereNotNull('sample_length')
            ->whereNotNull('length')
            ->where('sample_length', '>', 0)
            ->whereRaw('sample_length < (length - ' . self::LOW_RECOVERY_TOLERANCE . ')')
            ->where(function ($q) {
                $q->whereNull('comentarios')->orWhereRaw("TRIM(comentarios) = ''");
            });
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
     * Scope: Solo muestras que pueden tener Core Size (Originales y Duplicados).
     * Excluye expresamente Blancos y Estándares.
     */
    public function scopeEligibleForCoreSize(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            // Originales
            $q->where(function (Builder $orig) {
                $orig->whereRaw("UPPER(TRIM(sample_type)) = 'O'")
                    ->orWhereRaw("UPPER(TRIM(sample_type)) = 'ORIGINAL'")
                    ->orWhere(fn($s) => $s->whereNull('sample_type')->whereNull('control_type'));
            })
            // O Duplicados
            ->orWhere(function (Builder $dup) {
                $dup->whereNotNull('duplicate_sample_id')
                    ->orWhereRaw("UPPER(TRIM(control_type)) = 'DUPLICATE'")
                    ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%DUP%'");
            });
        })->where(function (Builder $q) {
            // Excluir explícitamente Blancos y Estándares
            $q->where(function (Builder $sub) {
                $sub->whereNull('standard_sample_id')
                    ->where(function ($ctrl) {
                        $ctrl->whereNull('control_type')
                            ->orWhere(function ($c) {
                                $c->whereRaw("UPPER(TRIM(control_type)) NOT LIKE '%STAND%'")
                                  ->whereRaw("UPPER(TRIM(control_type)) NOT LIKE 'STD-%'")
                                  ->whereRaw("UPPER(TRIM(control_type)) NOT LIKE '%BLANK%'")
                                  ->whereRaw("UPPER(TRIM(control_type)) NOT LIKE '%BLK%'")
                                  ->whereRaw("UPPER(TRIM(control_type)) NOT LIKE '%BLANCO%'")
                                  ->whereRaw("UPPER(TRIM(control_type)) != 'BCO'");
                            });
                    });
            });
        });
    }

    /**
     * Scope: Muestras inelegibles para Core Size (Blancos y Estándares).
     */
    public function scopeIneligibleForCoreSize(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('standard_sample_id')
                ->orWhereRaw("UPPER(TRIM(control_type)) = 'STANDARD'")
                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%STAND%'")
                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE 'STD-%'")
                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%BLANK%'")
                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%BLK%'")
                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%BLANCO%'")
                ->orWhereRaw("UPPER(TRIM(control_type)) = 'BCO'");
        });
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
