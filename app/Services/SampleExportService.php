<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampleExportService
{
    /**
     * Exporta una colección de muestras (DrillHoleSample) a un archivo Excel (.xlsx).
     *
     * @param  Collection<int, DrillHoleSample>  $samples
     * @param  string|null  $bhid  Nombre o código del barreno para nombrar el archivo
     */
    public function exportToExcel(Collection $samples, ?string $bhid = null): StreamedResponse
    {
        // 1. Cargar relaciones requeridas de forma ansiosa
        if ($samples instanceof \Illuminate\Database\Eloquent\Collection) {
            $samples->loadMissing([
                'barreno.proyecto',
                'proyecto',
                'standardSample',
                'duplicateSample',
                'workOrder',
                'qcPhotos',
            ]);
        }

        // 2. Crear Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Muestras');

        // Definición de columnas y encabezados
        $headers = [
            'A1' => 'BHID',
            'B1' => 'Sample #',
            'C1' => 'From (m)',
            'D1' => 'To (m)',
            'E1' => 'Length (m)',
            'F1' => 'Recovery (m)',
            'G1' => 'Core Size',
            'H1' => 'Sample Type',
            'I1' => 'Control',
            'J1' => 'Peso (kg)',
            'K1' => 'Work Order',
            'L1' => 'Fotos QC',
            'M1' => 'Estado',
            'N1' => 'Observaciones / Errores',
            'O1' => 'Comentarios',
            'P1' => 'Fecha Muestreo',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $lastCol = 'P';
        $headerRange = "A1:{$lastCol}1";

        // Estilos de la fila de encabezados
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'], // Azul corporativo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => false,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // 3. Escribir filas de datos
        $row = 2;
        foreach ($samples as $sample) {
            $sheet->getRowDimension($row)->setRowHeight(20);

            // BHID
            $sheet->setCellValue("A{$row}", $sample->barreno?->nombre_barreno ?? '');

            // Sample Number como String explícito para no perder ceros o caracteres
            $sheet->setCellValueExplicit("B{$row}", (string) $sample->sample_number, DataType::TYPE_STRING);

            // From Depth
            if ($sample->from_depth !== null && $sample->from_depth !== '') {
                $sheet->setCellValue("C{$row}", (float) $sample->from_depth);
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("C{$row}", '');
            }

            // To Depth
            if ($sample->to_depth !== null && $sample->to_depth !== '') {
                $sheet->setCellValue("D{$row}", (float) $sample->to_depth);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("D{$row}", '');
            }

            // Length
            if ($sample->length !== null && $sample->length !== '') {
                $sheet->setCellValue("E{$row}", (float) $sample->length);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("E{$row}", '');
            }

            // Sample Length (Recuperación)
            if ($sample->sample_length !== null && $sample->sample_length !== '') {
                $sheet->setCellValue("F{$row}", (float) $sample->sample_length);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("F{$row}", '');
            }

            // Core Size
            $sheet->setCellValue("G{$row}", !empty($sample->core_size) ? $sample->core_size : 'Sin asignar');

            // Sample Type
            $isControl = strtoupper(trim((string) $sample->sample_type)) === 'CONTROL';
            $sheet->setCellValue("H{$row}", $isControl ? 'Control' : 'O');

            // Control Type
            $sheet->setCellValue("I{$row}", $this->resolveControlDisplay($sample));

            // Weight
            if ($sample->weight !== null && $sample->weight !== '') {
                $sheet->setCellValue("J{$row}", (float) $sample->weight);
                $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("J{$row}", '');
            }

            // Work Order
            $sheet->setCellValue("K{$row}", $sample->workOrder?->work_order_code ?? 'Sin asignar');

            // Fotos QC
            if (!$sample->requiresQcPhotos()) {
                $sheet->setCellValue("L{$row}", '—');
            } else {
                $photosCount = $sample->qc_photos_count ?? $sample->qcPhotos->count();
                $sheet->setCellValue("L{$row}", "{$photosCount}/" . DrillHoleSample::QC_PHOTOS_REQUIRED);
            }

            // Estado y Errores
            $hasErrors = $sample->errors !== null && count((array) $sample->errors) > 0;
            $sheet->setCellValue("M{$row}", $hasErrors ? 'Con Errores' : 'Sin Errores');
            $sheet->setCellValue("N{$row}", $hasErrors ? implode(' | ', (array) $sample->errors) : '');

            // Comentarios
            $sheet->setCellValue("O{$row}", $sample->comentarios ?? '');

            // Fecha Muestreo
            $sheet->setCellValue("P{$row}", $sample->sampled_at ? $sample->sampled_at->format('d/m/Y H:i') : '');

            // Cebra (alternancia sutil de color)
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);
            }

            $row++;
        }

        $lastRow = max($row - 1, 1);

        // Alineación central para columnas clave
        foreach (['A', 'B', 'G', 'H', 'I', 'K', 'L', 'M', 'P'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Alineación a la derecha para valores numéricos
        foreach (['C', 'D', 'E', 'F', 'J'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Alineación a la izquierda para campos de texto descriptivo
        foreach (['N', 'O'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Bordes finos en toda la grilla de datos
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        // Autoajuste de ancho por columna
        foreach (range('A', $lastCol) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Auto filtro y congelar encabezado
        $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
        $sheet->freezePane('A2');

        // Nombre de archivo sanitizado
        $bhidName = $bhid ?: ($samples->first()?->barreno?->nombre_barreno ?: 'Export');
        $cleanBhid = preg_replace('/[^A-Za-z0-9_-]/', '_', $bhidName);
        $dateStr = now()->format('Ymd_His');
        $filename = "Muestras_{$cleanBhid}_{$dateStr}.xlsx";

        return response()->streamDownload(
            function () use ($spreadsheet) {
                if (ob_get_length()) {
                    ob_clean();
                }
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
     * Resuelve el nombre visual del control de acuerdo a la lógica de Coreflow.
     */
    protected function resolveControlDisplay(DrillHoleSample $sample): string
    {
        $sampleType = strtoupper(trim((string) $sample->sample_type));
        if ($sampleType !== 'CONTROL') {
            return '—';
        }

        $controlType = strtoupper(trim((string) $sample->control_type));

        // 1. Blanco
        if (str_contains($controlType, 'BLANK')) {
            return 'Blank-ML';
        }

        // 2. Standard
        if (str_contains($controlType, 'STAND') || $sample->standard_sample_id) {
            $standardName = $sample->standardSample?->standard_name;
            if (!$standardName && !empty($sample->control_type) && !in_array($controlType, ['STANDARD', 'STAND'])) {
                $standardName = StandardSample::where('standard_name', $sample->control_type)->value('standard_name');
            }
            return $standardName ?? $sample->control_type ?? 'Standard';
        }

        // 3. Duplicado
        if (str_contains($controlType, 'DUP') || $sample->duplicate_sample_id) {
            $dupNumber = $sample->duplicateSample?->sample_number;
            if (!$dupNumber && $sample->duplicate_sample_id) {
                $dupNumber = DrillHoleSample::where('id', $sample->duplicate_sample_id)->value('sample_number');
            }
            return $dupNumber ? "DUP-{$dupNumber}" : 'DUP';
        }

        // 4. Coincidencia directa con catálogo de estándares
        $matchedStandard = StandardSample::where('standard_name', $sample->control_type)->value('standard_name');
        if ($matchedStandard) {
            return $matchedStandard;
        }

        return $sample->control_type ?: 'Blank-ML';
    }
}
