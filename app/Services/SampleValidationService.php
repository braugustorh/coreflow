<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SampleValidationService
{
    /**
     * Validates all draft samples for a specific user.
     * Updates the `errors` column in the database for each record.
     */
    public function validateDraftsForUser(int $userId): void
    {
        $drafts = DrillHoleSample::where('user_id', $userId)
            ->where('status', 'draft')
            ->get();

        // Used for duplicate check among drafts (Rule g)
        $sampleNumbers = $drafts->pluck('sample_number')->filter()->toArray();
        $sampleNumberCounts = array_count_values($sampleNumbers);

        // Group by BHID to check overlaps (Rule a)
        $groupedByBhid = $drafts->groupBy('bhid');

        // We update records in bulk to be efficient, but for now we can iterate and save individually
        // Since we are validating user's active session drafts.
        DB::transaction(function () use ($drafts, $groupedByBhid, $sampleNumberCounts) {
            foreach ($groupedByBhid as $bhid => $samples) {
                // Sort by 'from' ascending
                $sortedSamples = $samples->sortBy('from')->values();
                $previousTo = null;

                foreach ($sortedSamples as $index => $sample) {
                    $errors = [];

                    // Rule e: BHID not empty
                    if (empty($sample->bhid)) {
                        $errors[] = 'BHID está vacío.';
                    }

                    // Rule a: Intervals "FROM" and "TO" verification (no overlap)
                    if ($sample->from !== null && $sample->to !== null) {
                        if ($previousTo !== null && $sample->from < $previousTo) {
                            $errors[] = 'Traslape detectado: El valor FROM (' . $sample->from . ') es menor que el TO anterior (' . $previousTo . ').';
                        }
                        $previousTo = max($previousTo, $sample->to);
                    }

                    // Rule b: Muestras: TO - FROM = DRILLED_LENGTH
                    if (
                        $sample->from !== null && 
                        $sample->to !== null && 
                        $sample->drilled_length !== null
                    ) {
                        $diff = abs(($sample->to - $sample->from) - $sample->drilled_length);
                        // Using a small tolerance for floating point comparison
                        if ($diff > 0.001) {
                            $errors[] = 'La diferencia de TO - FROM no corresponde al DRILLED_LENGTH.';
                        }
                    }

                    // Rule c: Si SAMPLE_TYPE == "O", FROM y TO no deben estar vacíos
                    // Treating explicit string 'O'
                    if (strtoupper((string) $sample->sample_type) === 'O') {
                        if ($sample->from === null || $sample->to === null) {
                            $errors[] = 'Si SAMPLE_TYPE es "O", FROM y TO son obligatorios.';
                        }
                    }

                    // Rule d: Si SAMPLE_TYPE == "Control", CONTROL_TYPE no debe estar vacío
                    if (strtoupper(trim((string) $sample->sample_type)) === 'CONTROL' || strtoupper(trim((string) $sample->sample_type)) === 'CONTROL_TYPE') {
                        // The user said "Control" but case insensitive is safer
                        if (empty($sample->control_type)) {
                            $errors[] = 'Si SAMPLE_TYPE es "Control", CONTROL_TYPE no debe estar vacío.';
                        }
                    }

                    // Rule f: WGHT no vacío y mayor a 0
                    if ($sample->wght === null) {
                        $errors[] = 'WGHT está vacío.';
                    } elseif ($sample->wght <= 0) {
                        $errors[] = 'WGHT debe ser mayor a 0.';
                    }

                    // Rule g: SAMPLE_NUMBER no vacío y único en este documento (drafts)
                    if (empty($sample->sample_number)) {
                        $errors[] = 'SAMPLE_NUMBER está vacío.';
                    } else {
                        if (isset($sampleNumberCounts[$sample->sample_number]) && $sampleNumberCounts[$sample->sample_number] > 1) {
                            $errors[] = 'SAMPLE_NUMBER duplicado en este bloque de datos.';
                        }
                    }

                    // Rule h: (Simulated) SAMPLE_NUMBER unique in the whole project
                    /*
                    if (!empty($sample->sample_number)) {
                        $existsInProject = DrillHoleSample::where('status', 'official')
                            ->where('sample_number', $sample->sample_number)
                            ->exists();
                        if ($existsInProject) {
                            $errors[] = 'SAMPLE_NUMBER ya existe previamente en este proyecto.';
                        }
                    }
                    */

                    // Save errors
                    $sample->errors = count($errors) > 0 ? $errors : null;
                    $sample->save();
                }
            }
        });
    }
}
