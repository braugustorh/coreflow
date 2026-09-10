<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\SampleSetting;
use App\Models\WorkOrder;
use Exception;
use Illuminate\Support\Facades\Log;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SackFormGeneratorService
{
    /**
     * Genera y retorna una respuesta de descarga en formato Excel (.xlsx)
     * del Registro de Costales para la Work Order proporcionada.
     */
    public function generate(WorkOrder $workOrder): StreamedResponse
    {
        // 1. Cargar las muestras asociadas ordenadas por número de muestra
        $samples = DrillHoleSample::where('work_order_id', $workOrder->id)
            ->with(['proyecto', 'barreno', 'duplicateSample'])
            ->orderByRaw('CAST(sample_number AS UNSIGNED), sample_number')
            ->get();

        if ($samples->isEmpty()) {
            abort(422, 'La Work Order no tiene muestras asignadas.');
        }

        // 2. Cargar la plantilla Excel
        $templatePath = storage_path('app/templates/formatoCostalTemplate.xlsx');

        if (!file_exists($templatePath)) {
            // Fallback a la ubicación de descarga
            $templatePath = 'C:\\Users\\braul\\Downloads\\formatoCostalTemplate.xlsx';
        }

        if (!file_exists($templatePath)) {
            abort(500, "Plantilla de costales no encontrada en {$templatePath}");
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 3. Obtener datos de cabecera
        $firstSample  = $samples->first();
        $proyecto     = $firstSample->proyecto;
        $sede         = $workOrder->sede ?? $proyecto?->sede;

        $supervisor = $samples
            ->whereNotNull('responsible_supervisor')
            ->first()?->responsible_supervisor
            ?? auth()->user()?->name
            ?? 'Geólogo';

        $totalSamples = $workOrder->samples_quantity ?: $samples->count();

        // 4. Agrupar muestras en sacos (costales) aplicando el límite y factores configurados en Samples Settings
        $settings      = SampleSetting::getSettings();
        $maxWeight     = (float) ($settings->max_sack_weight ?? 25.0);
        $defaultWeight = (float) config('coreflow.default_sample_weight', 1.0);
        $sacks         = $this->buildSacks($samples, $maxWeight, $defaultWeight, $settings);
        $totalSacks    = count($sacks);

        // 5. Llenar encabezados generales según las posiciones exactas del formato
        $sheet->setCellValue('E2', $workOrder->work_order_code);

        // Columna B (Merged B:C)
        $sheet->setCellValue('B7', 'Drill Core');
        $sheet->setCellValue('B8', (string) $totalSamples);
        $sheet->setCellValue('B9', (string) $totalSacks);
        $sheet->setCellValue('B10', 'Bureau Veritas');
        $sheet->setCellValue('B11', now()->format('d/m/Y'));

        // Columna E (Fila 7-11)
        $sheet->setCellValue('E7', $proyecto?->nombre ?? 'Media Luna');
        $sheet->setCellValue('E8', $sede?->name ?? 'San Miguel');
        $sheet->setCellValue('E9', $supervisor);
        $sheet->setCellValue('E10', ''); // Vehículo en blanco
        $sheet->setCellValue('E11', ''); // Conductor en blanco

        // 6. Escribir los costales y sus muestras aplicando los colores y alturas de fila legibles (20pt)
        $this->populateSacksGrid($sheet, $sacks);

        // 7. Preparar la descarga StreamedResponse compatible con Filament/Livewire
        $filename = "Registro_Costales_WO_{$workOrder->work_order_code}.xlsx";

        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    /**
     * Agrupa las muestras en costales asegurando que la suma de sus pesos no sobrepase estrictamente $maxWeight (25.0 kg).
     *
     * @param iterable<DrillHoleSample> $samples
     * @return array<int, array{number: int, weight: float, samples: array<string>}>
     */
    public function buildSacks($samples, float $maxWeight, float $defaultWeight, ?SampleSetting $settings = null): array
    {
        $settings = $settings ?? SampleSetting::getSettings();
        $sacks = [];
        $currentSack = [
            'number'  => 1,
            'weight'  => 0.0,
            'samples' => [],
        ];

        foreach ($samples as $sample) {
            $w = $this->resolveSampleWeight($sample, $settings, $defaultWeight);

            // Si agregar esta muestra superaría estrictamente el peso máximo y el costal ya tiene muestras, cerrar y abrir otro costal
            if (!empty($currentSack['samples']) && (($currentSack['weight'] + $w) > ($maxWeight + 0.0001))) {
                $sacks[] = $currentSack;
                $currentSack = [
                    'number'  => count($sacks) + 1,
                    'weight'  => 0.0,
                    'samples' => [],
                ];
            }

            $currentSack['samples'][] = $sample->sample_number;
            $currentSack['weight']    = round($currentSack['weight'] + $w, 3);
        }

        if (!empty($currentSack['samples'])) {
            $sacks[] = $currentSack;
        }

        return $sacks;
    }

    /**
     * Resuelve el peso real (medido en báscula) o estimado dinámicamente según diámetro y longitud.
     */
    public function resolveSampleWeight($sample, SampleSetting $settings, float $defaultWeight = 1.0): float
    {
        // 1. Si la muestra ya cuenta con peso físico registrado en báscula (> 0), usarlo
        if (!empty($sample->weight) && (float) $sample->weight > 0) {
            return (float) $sample->weight;
        }

        // 2. Muestras de Control (QA/QC)
        $isControl = strtolower((string) $sample->sample_type) === 'control' || !empty($sample->control_type);
        if ($isControl) {
            $controlType = strtolower((string) $sample->control_type);

            if (str_contains($controlType, 'blanco') || str_contains($controlType, 'blank')) {
                return (float) ($settings->blank_weight ?? 3.00);
            }

            if (str_contains($controlType, 'estándar') || str_contains($controlType, 'estandar') || str_contains($controlType, 'standard')) {
                return (float) ($settings->standard_weight ?? 0.065);
            }

            if (str_contains($controlType, 'duplicado') || str_contains($controlType, 'duplicate')) {
                $ratio = ((float) ($settings->duplicate_ratio ?? 50.0)) / 100.0;

                // Buscar la muestra original vinculada
                $origWeight = null;
                if ($sample->relationLoaded('duplicateSample') && $sample->duplicateSample) {
                    $origWeight = $this->resolveSampleWeight($sample->duplicateSample, $settings, $defaultWeight);
                } elseif (!empty($sample->duplicate_sample_id)) {
                    $orig = DrillHoleSample::find($sample->duplicate_sample_id);
                    if ($orig) {
                        $origWeight = $this->resolveSampleWeight($orig, $settings, $defaultWeight);
                    }
                }

                if ($origWeight !== null && $origWeight > 0) {
                    return round($origWeight * $ratio, 3);
                }

                // Fallback si no se encontró la original vinculada
                return round(3.00 * $ratio, 3);
            }
        }

        // 3. Muestra Original ('O'): calcular por diámetro (core_size) y longitud
        // Priorizar sample_length (recuperación de núcleo), con fallback a length o to_depth - from_depth
        $length = (float) ($sample->sample_length > 0 ? $sample->sample_length : ($sample->length > 0 ? $sample->length : 0));
        if ($length <= 0 && $sample->from_depth !== null && $sample->to_depth !== null) {
            $length = max(0, (float) $sample->to_depth - (float) $sample->from_depth);
        }

        // Determinar core_size (de la muestra o del barreno asociado)
        $coreSize = strtoupper(trim((string) ($sample->core_size ?: $sample->barreno?->core_size)));

        $factor = null;
        if (str_starts_with($coreSize, 'PQ')) {
            $factor = (float) ($settings->factor_pq ?? 7.66);
        } elseif (str_starts_with($coreSize, 'HQ')) {
            $factor = (float) ($settings->factor_hq ?? 4.28);
        } elseif (str_starts_with($coreSize, 'NQ')) {
            $factor = (float) ($settings->factor_nq ?? 2.40);
        } elseif (str_starts_with($coreSize, 'BQ')) {
            $factor = (float) ($settings->factor_bq ?? 1.40);
        }

        if ($factor !== null && $length > 0) {
            return round($length * $factor, 3);
        }

        // 4. Fallback final
        return $defaultWeight;
    }

    /**
     * Rellena la cuadrícula de costales aplicando estilos exactos y asegurando altura de fila uniforme (20pt):
     * - Encabezado del costal: Fondo #2E5C8A, texto blanco en negrita, altura 22pt.
     * - Celdas de muestras: Fondo #F2F2F2, texto centrado con bordes, altura 20pt.
     */
    private function populateSacksGrid($sheet, array $sacks): void
    {
        // Limpiar filas de bloques estáticos pre-diseñados (filas 19 a 48)
        $sheet->removeRow(19, 30);

        if (empty($sacks)) {
            return;
        }

        // Agrupar los costales en bloques de 5
        $sackBlocks = array_chunk($sacks, 5);
        $colsMap    = ['A', 'B', 'C', 'D', 'E'];

        $currentRow = 14; // Fila donde inicia el Bloque 0

        foreach ($sackBlocks as $blockIndex => $blockSacks) {
            // Calcular el número de muestras del costal más lleno en este bloque (mínimo 4)
            $maxSamplesInBlock = 4;
            foreach ($blockSacks as $sack) {
                if (count($sack['samples']) > $maxSamplesInBlock) {
                    $maxSamplesInBlock = count($sack['samples']);
                }
            }

            if ($blockIndex === 0) {
                // Bloque 0: Ya existe en la plantilla (Fila 14 encabezados, 15..18 datos)
                if ($maxSamplesInBlock > 4) {
                    $extraRows = $maxSamplesInBlock - 4;
                    $insertAt  = 19; // Justo después de la fila 18
                    $sheet->insertNewRowBefore($insertAt, $extraRows);
                }

                // Ajustar altura de la fila de encabezado
                $sheet->getRowDimension(14)->setRowHeight(22);

                // Aplicar estilo de encabezado y muestras para Bloque 0
                foreach ($blockSacks as $sackInBlockIdx => $sack) {
                    $colLetter  = $colsMap[$sackInBlockIdx];
                    $headerCell = "{$colLetter}14";
                    $sheet->setCellValue($headerCell, "COSTAL " . ($sack['number']));
                    $this->applySackHeaderStyle($sheet, $headerCell, 14);

                    foreach ($sack['samples'] as $sampleIdx => $sampleCode) {
                        $dataRow  = 15 + $sampleIdx;
                        $dataCell = "{$colLetter}{$dataRow}";
                        $sheet->setCellValue($dataCell, $sampleCode);
                        $this->applySampleCellStyle($sheet, $dataCell, $dataRow);
                    }
                }

                // Asegurar que cualquier celda de muestra vacía en el bloque 0 tenga también altura uniforme de 20pt
                for ($r = 0; $r < $maxSamplesInBlock; $r++) {
                    $sheet->getRowDimension(15 + $r)->setRowHeight(20);
                }

                $currentRow = 14 + 1 + $maxSamplesInBlock; // Siguiente fila disponible
            } else {
                // Bloques 1, 2, 3... (Costales 6-10, 11-15, etc.): Insertar bloque dinámico completo
                $totalBlockRows = 1 + $maxSamplesInBlock; // 1 header + N data rows
                $sheet->insertNewRowBefore($currentRow, $totalBlockRows);

                // 1. Configurar fila de encabezados del nuevo bloque
                $headerRow = $currentRow;
                $sheet->getRowDimension($headerRow)->setRowHeight(22);

                foreach ($blockSacks as $sackInBlockIdx => $sack) {
                    $colLetter  = $colsMap[$sackInBlockIdx];
                    $headerCell = "{$colLetter}{$headerRow}";
                    $sheet->setCellValue($headerCell, "COSTAL " . ($sack['number']));
                    $this->applySackHeaderStyle($sheet, $headerCell, $headerRow);
                }

                // 2. Configurar filas de datos y escribir muestras
                for ($r = 0; $r < $maxSamplesInBlock; $r++) {
                    $sheet->getRowDimension($headerRow + 1 + $r)->setRowHeight(20);
                }

                foreach ($blockSacks as $sackInBlockIdx => $sack) {
                    $colLetter = $colsMap[$sackInBlockIdx];
                    foreach ($sack['samples'] as $sampleIdx => $sampleCode) {
                        $dataRow  = $headerRow + 1 + $sampleIdx;
                        $dataCell = "{$colLetter}{$dataRow}";
                        $sheet->setCellValue($dataCell, $sampleCode);
                        $this->applySampleCellStyle($sheet, $dataCell, $dataRow);
                    }
                }

                $currentRow = $headerRow + $totalBlockRows;
            }
        }
    }

    /**
     * Aplica el estilo requerido para el encabezado del costal (#2E5C8A, texto blanco centrado en negrita) y fija la altura a 22pt.
     */
    private function applySackHeaderStyle($sheet, string $cellCoordinate, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(22);
        $sheet->getStyle($cellCoordinate)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E5C8A'],
            ],
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size'  => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF1B365D'],
                ],
            ],
        ]);
    }

    /**
     * Aplica el estilo requerido para la celda de muestra (#F2F2F2, texto centrado) y fija la altura a 20pt.
     */
    private function applySampleCellStyle($sheet, string $cellCoordinate, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle($cellCoordinate)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF2F2F2'],
            ],
            'font' => [
                'bold'  => false,
                'color' => ['argb' => 'FF000000'],
                'size'  => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFD9D9D9'],
                ],
            ],
        ]);
    }
}
