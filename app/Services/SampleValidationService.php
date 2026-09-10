<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use Illuminate\Support\Facades\DB;

class SampleValidationService
{
    /**
     * Valida todos los borradores de importación para un usuario específico.
     * Actualiza la columna `errors` en la BD por cada registro.
     */
    public function validateDraftsForUser(int $userId): void
    {
        $drafts = DrillHoleSample::where('user_id', $userId)
            ->where('status', 'draft')
            ->where('capture_source', 'import')
            ->get();

        $this->executeValidationOnSamples($drafts);
    }

    /**
     * Valida todos los borradores de importación para un barreno específico.
     * Actualiza la columna `errors` en la BD por cada registro.
     */
    public function validateDraftsForBarreno(int $barrenoId): void
    {
        $drafts = DrillHoleSample::where('barreno_id', $barrenoId)
            ->where('status', 'draft')
            ->where('capture_source', 'import')
            ->get();

        $this->executeValidationOnSamples($drafts);
    }

    /**
     * Motor central de validación transaccional sobre un conjunto de muestras borrador.
     */
    public function executeValidationOnSamples(\Illuminate\Support\Collection $drafts): void
    {
        if ($drafts->isEmpty()) {
            return;
        }

        // Para detección de duplicados dentro del lote (Regla g)
        $sampleNumbers = $drafts->pluck('sample_number')->filter()->toArray();
        $sampleNumberCounts = array_count_values($sampleNumbers);

        // Agrupar por barreno para verificar traslapes (Regla a)
        $groupedByBarreno = $drafts->groupBy('barreno_id');

        DB::transaction(function () use ($drafts, $groupedByBarreno, $sampleNumberCounts) {
            foreach ($groupedByBarreno as $barrenoId => $samples) {

                // Precalcular traslapes: solo sobre originales con from/to definidos
                $originals = $samples
                    ->filter(fn($s) => $s->from_depth !== null && $s->to_depth !== null)
                    ->sortBy(fn($s) => (float) $s->from_depth)
                    ->values();

                $overlapErrors = [];
                $gapWarnings   = [];
                $previousTo    = null;
                $previousSampleId = null;

                // Obtener validaciones de gaps existentes para este barreno
                $existingGapValidations = \App\Models\DrillHoleGapValidation::where('barreno_id', $barrenoId)->get();
                $unvalidatedGapsToNotify = [];

                foreach ($originals as $sample) {
                    $from = (float) $sample->from_depth;
                    $to   = (float) $sample->to_depth;

                    if ($previousTo !== null) {
                        $diff = round($from - $previousTo, 3);

                        if ($from < $previousTo) {
                            // Traslape
                            $overlapErrors[$sample->id] = "Traslape: FROM ({$from}) < TO anterior ({$previousTo}).";
                        } elseif ($diff > 0.011) {
                            // Verificar si este tramo (de $previousTo a $from) ya está cubierto por validaciones de gap
                            $isValidated = $existingGapValidations->contains(function ($gv) use ($previousTo, $from) {
                                return ((float)$gv->from_depth <= $previousTo + 0.01) && ((float)$gv->to_depth >= $from - 0.01);
                            });

                            if (!$isValidated) {
                                $gapWarnings[$sample->id] = "GAP de {$diff} m sin validar (de {$previousTo}m a {$from}m). El Administrador o Supervisor Coreshack debe validar la razón de no muestreo en Barrenos.";
                                $unvalidatedGapsToNotify[] = [
                                    'barreno_id' => $barrenoId,
                                    'from'       => $previousTo,
                                    'to'         => $from,
                                    'diff'       => $diff,
                                ];
                            }
                        }
                    }
                    $previousTo       = max($previousTo ?? 0, $to);
                    $previousSampleId = $sample->id;
                }

                // Notificar a super_admin, Administradores y Supervisores Coreshack sobre gaps sin validar
                if (!empty($unvalidatedGapsToNotify)) {
                    $notifiableUsers = \App\Models\User::where(function ($query) {
                        $query->whereHas('roles', function ($q) {
                            $q->whereIn('name', ['super_admin', 'super-admin', 'Administrador', 'Admin', 'Supervisor Coreshack', 'Supervisor']);
                        })
                        ->orWhere('id', 1)
                        ->orWhere('email', 'like', '%admin%');
                    })->get();

                    if ($notifiableUsers->isEmpty()) {
                        $notifiableUsers = \App\Models\User::all();
                    }

                    $barrenoModel = \App\Models\DrillHole::find($barrenoId);
                    $barrenoName  = $barrenoModel?->nombre_barreno ?? 'ID ' . $barrenoId;

                    foreach ($unvalidatedGapsToNotify as $gInfo) {
                        \Illuminate\Support\Facades\Notification::send(
                            $notifiableUsers,
                            new \App\Notifications\GapValidationRequiredNotification(
                                $barrenoName,
                                (float) $gInfo['from'],
                                (float) $gInfo['to'],
                                (float) $gInfo['diff']
                            )
                        );
                    }
                }

                // Validar cada muestra del barreno
                foreach ($samples as $sample) {
                    $errors = [];
                    $isControl = strtoupper(trim((string) $sample->sample_type)) === 'CONTROL';

                    // Regla a: Traslape entre intervalos
                    if (isset($overlapErrors[$sample->id])) {
                        $errors[] = $overlapErrors[$sample->id];
                    }

                    // Advertencia: GAP / Hueco entre intervalos consecutivos
                    if (isset($gapWarnings[$sample->id])) {
                        $errors[] = $gapWarnings[$sample->id];
                    }

                    // Regla b: TO - FROM debe coincidir con length (solo originales)
                    if (!$isControl &&
                        $sample->from_depth !== null &&
                        $sample->to_depth   !== null &&
                        $sample->length     !== null
                    ) {
                        $calculated = round((float) $sample->to_depth - (float) $sample->from_depth, 3);
                        $stored     = round((float) $sample->length, 3);
                        if (abs($calculated - $stored) > 0.011) {
                            $errors[] = "TO - FROM ({$calculated}) no coincide con la Longitud ({$stored}).";
                        }
                    }

                    // Regla: sample_length (recuperación) no puede ser mayor a length
                    if (!$isControl &&
                        $sample->sample_length !== null &&
                        $sample->length        !== null &&
                        (float) $sample->sample_length > ((float) $sample->length + 0.001)
                    ) {
                        $errors[] = "Recuperación ({$sample->sample_length}) no puede ser mayor a la Longitud ({$sample->length}).";
                    }

                    // Regla c: Si sample_type == 'O', from_depth y to_depth son obligatorios
                    if (strtoupper(trim((string) $sample->sample_type)) === 'O') {
                        if ($sample->from_depth === null || $sample->to_depth === null) {
                            $errors[] = 'Para muestras tipo "O", FROM y TO son obligatorios.';
                        }
                        if ($sample->from_depth !== null && $sample->to_depth !== null &&
                            (float) $sample->from_depth >= (float) $sample->to_depth) {
                            $errors[] = 'FROM debe ser menor que TO.';
                        }
                    }

                    // Regla d: Si sample_type == 'CONTROL', control_type no debe estar vacío
                    if ($isControl && empty($sample->control_type)) {
                        $errors[] = 'Para muestras de Control, el Tipo de Control no debe estar vacío.';
                    }

                    // Regla f: weight no vacío y dentro de límites configurados (solo originales)
                    if (!$isControl) {
                        if ($sample->weight === null) {
                            $errors[] = 'El peso (Weight) está vacío.';
                        } else {
                            $w = (float) $sample->weight;
                            $minSampleWeight = (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50);
                            $maxSampleWeight = (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00);

                            if ($w < $minSampleWeight) {
                                $errors[] = "El peso ({$w} kg) es inferior al mínimo permitido ({$minSampleWeight} kg).";
                            } elseif ($w > $maxSampleWeight) {
                                $errors[] = "El peso ({$w} kg) excede el máximo permitido ({$maxSampleWeight} kg).";
                            }
                        }
                    }

                    // Regla g: sample_number no vacío y único dentro del lote
                    if (empty($sample->sample_number)) {
                        $errors[] = 'El número de muestra está vacío.';
                    } else {
                        if (
                            isset($sampleNumberCounts[$sample->sample_number]) &&
                            $sampleNumberCounts[$sample->sample_number] > 1
                        ) {
                            $errors[] = 'Número de muestra duplicado en el archivo importado.';
                        }
                    }

                    // Regla h: sample_number único en registros ya oficiales del proyecto
                    if (!empty($sample->sample_number) && $sample->proyecto_id) {
                        $existsInProject = DrillHoleSample::where('status', 'official')
                            ->where('proyecto_id', $sample->proyecto_id)
                            ->where('sample_number', $sample->sample_number)
                            ->exists();
                        if ($existsInProject) {
                            $errors[] = 'El número de muestra ya existe en registros oficiales de este proyecto.';
                        }

                        // Verificación cruzada: Si ya existe en otro borrador activo de otro barreno en el mismo proyecto
                        $existsInOtherDraft = DrillHoleSample::where('status', 'draft')
                            ->where('capture_source', 'import')
                            ->where('proyecto_id', $sample->proyecto_id)
                            ->where('barreno_id', '!=', $sample->barreno_id)
                            ->where('sample_number', $sample->sample_number)
                            ->with(['user', 'barreno'])
                            ->first();

                        if ($existsInOtherDraft) {
                            $uploaderName = $existsInOtherDraft->user?->name ?? 'otro usuario';
                            $otherHole    = $existsInOtherDraft->barreno?->nombre_barreno ?? 'otro barreno';
                            $errors[]     = "Conflicto de folio: Esta muestra ya está en borrador en {$otherHole} (cargado por {$uploaderName}).";
                        }
                    }

                    // Guardar errores (null = sin errores = válido)
                    $sample->errors = count($errors) > 0 ? $errors : null;
                    $sample->save();
                }
            }
        });
    }
}
