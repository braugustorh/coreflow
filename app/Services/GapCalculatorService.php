<?php

namespace App\Services;

use App\Models\DrillHole;

class GapCalculatorService
{
    /**
     * Calcula todos los tramos faltantes (gaps) no cubiertos por muestras originales ni por validaciones previas.
     *
     * @param DrillHole $drillHole
     * @return array Array de arrays con ['from_depth' => float, 'to_depth' => float]
     */
    public static function calculateGaps(DrillHole $drillHole): array
    {
        $startDepth = (float) ($drillHole->start_depth ?? 0.0);
        $maxDepth   = (float) ($drillHole->max_depth ?? 0.0);

        if ($maxDepth <= $startDepth) {
            return [];
        }

        // Obtener solo muestras originales (excluir muestras de CONTROL que tienen from/to en 0)
        $samples = $drillHole->drillHoleSamples()
            ->where(function ($q) {
                $q->whereNull('sample_type')
                  ->orWhere('sample_type', 'O')
                  ->orWhere('sample_type', '!=', 'CONTROL');
            })
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->whereColumn('to_depth', '>', 'from_depth')
            ->select('from_depth', 'to_depth')
            ->get();

        // Obtener intervalos ya validados
        $validations = $drillHole->gapValidations()
            ->select('from_depth', 'to_depth')
            ->get();

        $intervals = [];

        foreach ($samples as $sample) {
            $from = (float) $sample->from_depth;
            $to = (float) $sample->to_depth;
            if ($to > $from) {
                $intervals[] = ['from' => $from, 'to' => $to];
            }
        }

        foreach ($validations as $validation) {
            $from = (float) $validation->from_depth;
            $to = (float) $validation->to_depth;
            if ($to > $from) {
                $intervals[] = ['from' => $from, 'to' => $to];
            }
        }

        if (empty($intervals)) {
            return [
                [
                    'from_depth' => $startDepth,
                    'to_depth'   => $maxDepth,
                ]
            ];
        }

        // Ordenar intervalos por profundidad de inicio
        usort($intervals, function ($a, $b) {
            return $a['from'] <=> $b['from'];
        });

        // Fusionar intervalos solapados o contiguos
        $merged = [];
        foreach ($intervals as $interval) {
            if (empty($merged)) {
                $merged[] = $interval;
            } else {
                $lastIndex = count($merged) - 1;
                if ($interval['from'] <= $merged[$lastIndex]['to'] + 0.001) {
                    $merged[$lastIndex]['to'] = max($merged[$lastIndex]['to'], $interval['to']);
                } else {
                    $merged[] = $interval;
                }
            }
        }

        // Calcular huecos (gaps)
        $gaps = [];
        $cursor = $startDepth;

        foreach ($merged as $interval) {
            if ($interval['from'] > $cursor + 0.001) {
                $gapTo = min($interval['from'], $maxDepth);
                if ($gapTo > $cursor) {
                    $gaps[] = [
                        'from_depth' => round($cursor, 2),
                        'to_depth'   => round($gapTo, 2),
                    ];
                }
            }
            $cursor = max($cursor, $interval['to']);
        }

        if ($cursor < $maxDepth - 0.001) {
            $gaps[] = [
                'from_depth' => round($cursor, 2),
                'to_depth'   => round($maxDepth, 2),
            ];
        }

        return $gaps;
    }

    /**
     * Devuelve una lista de bloques continuos fusionados (sample, validation, gap)
     * optimizada para la barra horizontal de UX.
     */
    public static function getVisualSegments(DrillHole $drillHole): array
    {
        $startDepth = (float) ($drillHole->start_depth ?? 0.0);
        $maxDepth   = (float) ($drillHole->max_depth ?? 0.0);
        $totalSpan  = $maxDepth - $startDepth;

        if ($totalSpan <= 0) {
            return [];
        }

        // Muestras originales
        $samples = $drillHole->drillHoleSamples()
            ->where(function ($q) {
                $q->whereNull('sample_type')
                  ->orWhere('sample_type', 'O')
                  ->orWhere('sample_type', '!=', 'CONTROL');
            })
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->whereColumn('to_depth', '>', 'from_depth')
            ->select('from_depth', 'to_depth')
            ->get();

        // Validaciones con datos del auditor/usuario
        $validations = $drillHole->gapValidations()
            ->with('validator')
            ->get();

        $intervals = [];

        foreach ($samples as $s) {
            $intervals[] = [
                'from'  => (float) $s->from_depth,
                'to'    => (float) $s->to_depth,
                'type'  => 'sample',
                'label' => 'Tramo Muestreado',
            ];
        }

        foreach ($validations as $v) {
            $intervals[] = [
                'from'         => (float) $v->from_depth,
                'to'           => (float) $v->to_depth,
                'type'         => 'validation',
                'label'        => 'Gap Validado (' . $v->reason . ')',
                'reason'       => $v->reason,
                'notes'        => $v->notes,
                'validated_by' => $v->validator?->name ?? 'Usuario',
                'validated_at' => $v->validated_at ? $v->validated_at->format('d/m/Y H:i') : null,
            ];
        }

        usort($intervals, fn($a, $b) => $a['from'] <=> $b['from']);

        // Fusionar bloques continuos del mismo tipo (solo si son muestras continuas o misma validación)
        $merged = [];
        foreach ($intervals as $inv) {
            if (empty($merged)) {
                $merged[] = $inv;
            } else {
                $lastIdx = count($merged) - 1;
                $isSampleMerge = $inv['type'] === 'sample' && $merged[$lastIdx]['type'] === 'sample';
                $isSameValidationMerge = $inv['type'] === 'validation'
                    && $merged[$lastIdx]['type'] === 'validation'
                    && ($inv['reason'] ?? '') === ($merged[$lastIdx]['reason'] ?? '')
                    && ($inv['notes'] ?? '') === ($merged[$lastIdx]['notes'] ?? '');

                if (($isSampleMerge || $isSameValidationMerge) && $inv['from'] <= $merged[$lastIdx]['to'] + 0.001) {
                    $merged[$lastIdx]['to'] = max($merged[$lastIdx]['to'], $inv['to']);
                } else {
                    $merged[] = $inv;
                }
            }
        }

        // Reconstruir la secuencia continua incluyendo los GAPs
        $blocks = [];
        $cursor = $startDepth;

        foreach ($merged as $block) {
            if ($block['from'] > $cursor + 0.001) {
                $gapTo = min($block['from'], $maxDepth);
                if ($gapTo > $cursor) {
                    $blocks[] = [
                        'from'        => round($cursor, 2),
                        'to'          => round($gapTo, 2),
                        'type'        => 'gap',
                        'label'       => '⚠️ GAP sin Muestrear',
                        'description' => 'Tramo sin información de muestras.',
                    ];
                }
            }

            $blocks[] = [
                'from'         => round($block['from'], 2),
                'to'           => round($block['to'], 2),
                'type'         => $block['type'],
                'label'        => $block['label'],
                'description'  => $block['type'] === 'sample' ? 'Tramo continuo de perforación muestreado.' : ($block['notes'] ?: 'Gap justificado por Geología.'),
                'reason'       => $block['reason'] ?? null,
                'notes'        => $block['notes'] ?? null,
                'validated_by' => $block['validated_by'] ?? null,
                'validated_at' => $block['validated_at'] ?? null,
            ];
            $cursor = max($cursor, $block['to']);
        }

        if ($cursor < $maxDepth - 0.001) {
            $blocks[] = [
                'from'        => round($cursor, 2),
                'to'          => round($maxDepth, 2),
                'type'        => 'gap',
                'label'       => '⚠️ GAP sin Muestrear',
                'description' => 'Tramo final sin información.',
            ];
        }

        // Calcular porcentajes CSS
        foreach ($blocks as &$b) {
            $length = round($b['to'] - $b['from'], 2);
            $b['length'] = $length;
            $b['percentage'] = round(($length / $totalSpan) * 100, 4);
        }

        return $blocks;
    }
}
