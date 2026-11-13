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

    /**
     * Indica si esta Work Order ya fue enviada al laboratorio.
     * Una WO se considera "enviada" cuando sus muestras tienen is_archived = true
     * (acción "Enviar" del Monitor de Muestreo).
     */
    public function isSent(): bool
    {
        return (bool) $this->sent_to_lab;
    }

    // ─── Eliminación / Desasociación controlada ──────────────────────────────

    /** Roles autorizados para eliminar o desasociar muestras de una Work Order. */
    public const MANAGER_ROLES = ['super_admin', 'Admin CoreFlow'];

    /** Roles autorizados para descargar formatos (Supervisor, Administrador y Super Admin). */
    public const FORMAT_DOWNLOAD_ROLES = [
        'super_admin',
        'Admin CoreFlow',
        'Admin',
        'Administrador',
        'Supervisor CoreS',
        'Supervisor Coreshack',
        'Supervisor',
        'supervisor',
    ];

    /** Estatus de WO que bloquean eliminar/desasociar (completada / liberada). */
    public const LOCKING_STATUSES = ['Liberado', 'Barreno completo en Lab'];

    /**
     * Indica si el usuario puede eliminar WOs o desasociar sus muestras.
     */
    public static function userCanManageAssociations(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole(self::MANAGER_ROLES);
    }

    /**
     * Indica si el usuario tiene rol de Supervisor, Administrador o Super Admin
     * autorizado para descargar los formatos de despacho.
     */
    public static function userCanDownloadFormats(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole(self::FORMAT_DOWNLOAD_ROLES);
    }

    /**
     * Motivo por el cual la WO NO puede eliminarse ni desasociar sus muestras
     * (null si la operación está permitida).
     *
     * Se bloquea cuando la WO:
     *  - ya fue enviada desde el Monitor de Muestreo (sent_to_lab o muestras archivadas),
     *  - tiene estatus logístico "Recibida" en Summary Samples (fecha de recepción),
     *  - tiene Work Order Status "Completada" (Barreno completo en Lab) o "Liberado".
     */
    public function getAssociationLockReason(): ?string
    {
        if ($this->sent_to_lab || $this->drillHoleSamples()->where('is_archived', true)->exists()) {
            return 'La Work Order ya fue enviada desde el Monitor de Muestreo.';
        }

        if (!empty($this->getRawOriginal('reception_date'))) {
            return 'La Work Order ya tiene estatus logístico "Recibida" en Summary Samples.';
        }

        if (in_array($this->status, self::LOCKING_STATUSES, true)) {
            return "La Work Order tiene el estatus \"{$this->status}\" en Summary Samples.";
        }

        return null;
    }

    public function canBeDeletedOrReleased(): bool
    {
        return $this->getAssociationLockReason() === null;
    }

    /**
     * Desvincula todas las muestras de la WO (quedan sin Work Order, conservando sus datos)
     * y recalcula el conteo. Devuelve cuántas muestras se desasociaron.
     */
    public function releaseSamples(): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function (): int {
            $ids = DrillHoleSample::where('work_order_id', $this->id)->pluck('id')->all();

            if (empty($ids)) {
                return 0;
            }

            $count = DrillHoleSample::whereIn('id', $ids)->update(['work_order_id' => null]);

            $this->forceFill(['samples_quantity' => 0])->save();

            return $count;
        });
    }

    // ─── Sincronización eficiente de samples_quantity ────────────────────────

    private static bool $suspendSampleQuantitySync = false;

    public static function isSampleQuantitySyncSuspended(): bool
    {
        return self::$suspendSampleQuantitySync;
    }

    /**
     * Ejecuta un bloque suspendiendo la sincronización automática por fila en el Observer
     * (útil en inserciones masivas donde el conteo se recalcula una sola vez al final).
     */
    public static function withoutSampleQuantitySync(callable $callback): mixed
    {
        $previous = self::$suspendSampleQuantitySync;
        self::$suspendSampleQuantitySync = true;

        try {
            return $callback();
        } finally {
            self::$suspendSampleQuantitySync = $previous;
        }
    }

    /**
     * Recalcula samples_quantity usando una única consulta indexada por cada Work Order afectada.
     *
     * @param  int|array<int, int|string|null>|null  $workOrderIds
     */
    public static function syncSamplesQuantity(int|array|null $workOrderIds): void
    {
        if ($workOrderIds === null) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, (array) $workOrderIds),
            static fn (int $id): bool => $id > 0
        )));

        foreach ($ids as $woId) {
            $count = DrillHoleSample::where('work_order_id', $woId)->count();
            self::where('id', $woId)->update(['samples_quantity' => $count]);
        }
    }
}
