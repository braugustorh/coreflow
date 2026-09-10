<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlsFormGeneratorService
{
    /**
     * Genera y retorna una StreamedResponse de descarga del formulario ALS PDF.
     * Se usa streamDownload() para ser compatible con Livewire/Filament (las
     * Actions operan sobre AJAX y no pueden retornar respuestas binarias directas).
     */
    public function generate(WorkOrder $workOrder): StreamedResponse
    {
        // 1. Cargar las muestras ordenadas por sample_number
        $samples = DrillHoleSample::where('work_order_id', $workOrder->id)
            ->with(['proyecto', 'barreno'])
            ->orderByRaw('CAST(sample_number AS UNSIGNED), sample_number')
            ->get();

        if ($samples->isEmpty()) {
            abort(422, 'La Work Order no tiene muestras asignadas.');
        }

        // 2. Obtener datos de cabecera
        $firstSample  = $samples->first();
        $proyecto     = $firstSample->proyecto;
        $sede         = $workOrder->sede ?? $proyecto?->sede;
        $companyName  = $sede?->name ?? 'Minera Media Luna';

        // Supervisor responsable (del primer registro que lo tenga)
        $supervisor = $samples
            ->whereNotNull('responsible_supervisor')
            ->first()?->responsible_supervisor ?? 'N/A';

        // Fecha de envío (del primer registro que la tenga)
        $sentDate = $samples
            ->whereNotNull('sent_date')
            ->first()?->sent_date?->format('d/m/Y') ?? now()->format('d/m/Y');

        // RUSH: true si al menos una muestra tiene rush = true
        $isRush = $samples->contains('rush', true);

        // Tiene core_size: si alguna muestra tiene core_size
        $hasCoreSize = $samples->whereNotNull('core_size')->isNotEmpty();

        // 3. Rango consolidado: primer número → último número, sin revelar tipos de control
        $sampleFrom   = $samples->first()->sample_number;
        $sampleTo     = $samples->last()->sample_number;
        $totalSamples = $workOrder->samples_quantity ?: $samples->count();

        $filename = "Formulario_ALS_WO_{$workOrder->work_order_code}.pdf";

        // 4. Renderizar la vista Blade y generar el PDF
        $pdf = Pdf::loadView('pdf.als-form', [
            'workOrder'    => $workOrder,
            'companyName'  => $companyName,
            'supervisor'   => $supervisor,
            'sentDate'     => $sentDate,
            'proyecto'     => $proyecto,
            'isRush'       => $isRush,
            'hasCoreSize'  => $hasCoreSize,
            'sampleFrom'   => $sampleFrom,
            'sampleTo'     => $sampleTo,
            'totalSamples' => $totalSamples,
        ])
        ->setPaper('letter', 'portrait')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', false)
        ->setOption('defaultFont', 'Helvetica');

        // streamDownload es la única forma compatible con Filament/Livewire:
        // Livewire intercepta el StreamedResponse antes de intentar serializarlo a JSON.
        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Agrupa las muestras en rangos correlativos continuos por número de muestra.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $samples
     * @return array<int, array{inicio: string, fin: string, cantidad: int, tipo: string}>
     */
    private function buildRangeGroups($samples): array
    {
        if ($samples->isEmpty()) {
            return [];
        }

        $groups      = [];
        $currentGroup = null;

        foreach ($samples as $sample) {
            $numericValue = $this->extractNumeric($sample->sample_number);

            if ($currentGroup === null) {
                // Iniciar primer grupo
                $currentGroup = [
                    'inicio'    => $sample->sample_number,
                    'fin'       => $sample->sample_number,
                    'cantidad'  => 1,
                    'tipo'      => '',
                    '_last_num' => $numericValue,
                ];
                continue;
            }

            $isConsecutive = ($numericValue !== null)
                && ($currentGroup['_last_num'] !== null)
                && ($numericValue === $currentGroup['_last_num'] + 1);

            if ($isConsecutive) {
                // Extender el rango actual
                $currentGroup['fin']      = $sample->sample_number;
                $currentGroup['cantidad'] += 1;
                $currentGroup['_last_num'] = $numericValue;
            } else {
                // Cerrar rango actual y abrir uno nuevo
                unset($currentGroup['_last_num']);
                $groups[]     = $currentGroup;
                $currentGroup = [
                    'inicio'    => $sample->sample_number,
                    'fin'       => $sample->sample_number,
                    'cantidad'  => 1,
                    'tipo'      => '',
                    '_last_num' => $numericValue,
                ];
            }
        }

        // Cerrar el último grupo
        if ($currentGroup !== null) {
            unset($currentGroup['_last_num']);
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Extrae la parte numérica al final del sample_number.
     * Ej: "ML000123" → 123 | "123456" → 123456 | "ABC" → null
     */
    private function extractNumeric(?string $sampleNumber): ?int
    {
        if (empty($sampleNumber)) {
            return null;
        }

        // Intentar convertir directamente si es numérico
        if (is_numeric($sampleNumber)) {
            return (int) $sampleNumber;
        }

        // Extraer sufijo numérico
        if (preg_match('/(\d+)$/', $sampleNumber, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
