<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\SampleSetting;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SackFormGeneratorService
{
    /**
     * Prepara todos los datos de cabecera, costales y saltos de serie para una Work Order.
     *
     * @return array{
     *     workOrder: WorkOrder,
     *     samples: \Illuminate\Database\Eloquent\Collection,
     *     proyectoNombre: string,
     *     sentFrom: string,
     *     geologo: string,
     *     sentDate: string,
     *     totalSamples: int,
     *     sacks: array<int, array{number: int, weight: float, samples: array<string>}>,
     *     totalSacks: int,
     *     seriesStarts: array<string, bool>
     * }
     */
    public function prepareData(WorkOrder $workOrder): array
    {
        // 1. Cargar las muestras asociadas ordenadas por número de muestra
        $samples = DrillHoleSample::where('work_order_id', $workOrder->id)
            ->with(['proyecto', 'barreno.proyecto', 'duplicateSample', 'geologist'])
            ->orderByRaw('CAST(sample_number AS UNSIGNED), sample_number')
            ->get();

        if ($samples->isEmpty()) {
            throw new RuntimeException('La Work Order no tiene muestras asignadas.');
        }

        // 2. Obtener datos de cabecera
        $proyectoNombre = $this->resolveProjectName($samples);
        $sentFrom       = $this->resolveSentFrom($workOrder, $samples);
        $geologo        = $this->resolveGeologistName($samples);
        $sentDate       = $this->resolveSentDate($workOrder, $samples);

        $totalSamples = $samples->count();

        // 3. Agrupar muestras en sacos (costales) aplicando el límite y factores configurados en Samples Settings
        $settings      = SampleSetting::getSettings();
        $maxWeight     = (float) ($settings->max_sack_weight ?? 25.0);
        $defaultWeight = (float) config('coreflow.default_sample_weight', 1.0);
        $sacks         = $this->buildSacks($samples, $maxWeight, $defaultWeight, $settings);
        $totalSacks    = count($sacks);
        $seriesStarts  = $this->detectSeriesStarts($samples);

        return compact(
            'workOrder',
            'samples',
            'proyectoNombre',
            'sentFrom',
            'geologo',
            'sentDate',
            'totalSamples',
            'sacks',
            'totalSacks',
            'seriesStarts'
        );
    }

    /**
     * Genera y retorna el binario PDF del Registro de Costales para integrarlo en el expediente unificado.
     */
    public function generatePdfBinary(WorkOrder $workOrder): string
    {
        $data = $this->prepareData($workOrder);

        $pdf = Pdf::loadView('pdf.sacks-form', $data)
            ->setPaper('letter', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'Helvetica');

        return $pdf->output();
    }

    /**
     * Genera y retorna una respuesta de descarga en formato Excel (.xlsx)
     * del Registro de Costales para la Work Order proporcionada.
     */
    public function generate(WorkOrder $workOrder, ?string $customTemplatePath = null): StreamedResponse
    {
        // 1. Preparar datos y validar existencia de muestras
        $data = $this->prepareData($workOrder);

        // 2. Cargar la plantilla Excel
        $templatePath = $this->resolveTemplatePath($customTemplatePath);

        if ($templatePath === null) {
            $searchedPaths = $customTemplatePath !== null
                ? [$customTemplatePath]
                : [
                    resource_path('templates/formatoCostalTemplate.xlsx'),
                    storage_path('app/templates/formatoCostalTemplate.xlsx'),
                ];

            Log::error('Plantilla de costales no encontrada.', [
                'work_order_id'   => $workOrder->id,
                'work_order_code' => $workOrder->work_order_code,
                'searched_paths'  => $searchedPaths,
            ]);

            throw new RuntimeException(
                'No se encontró la plantilla de costales (formatoCostalTemplate.xlsx) en el servidor.'
            );
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 3. Llenar encabezados generales según las posiciones exactas del formato
        $sheet->setCellValue('E2', $workOrder->work_order_code);

        // Columna B (Merged B:C)
        $sheet->setCellValue('B7', 'Drill Core');
        $sheet->setCellValue('B8', (string) $data['totalSamples']);
        $sheet->setCellValue('B9', (string) $data['totalSacks']);
        $sheet->setCellValue('B10', 'ALS');
        $sheet->setCellValue('B11', $data['sentDate']);

        // Columna E (Fila 7-11)
        $sheet->setCellValue('E7', $data['proyectoNombre']);
        $sheet->setCellValue('E8', $data['sentFrom']);
        $sheet->setCellValue('E9', $data['geologo']);
        $sheet->setCellValue('E10', ''); // Vehículo en blanco
        $sheet->setCellValue('E11', ''); // Conductor en blanco

        // 4. Escribir los costales y sus muestras aplicando los colores y alturas de fila legibles (20pt)
        $this->populateSacksGrid($sheet, $data['sacks'], $data['seriesStarts']);

        // 4.1 Fecha en "Firmas de Autorización" (desacoplada de fecha de envío, en blanco para firma manuscrita)
        $this->writePrintDate($sheet, null);

        // 5. Preparar la descarga StreamedResponse compatible con Filament/Livewire
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
     * Nombres únicos de proyecto tomados del barreno de las muestras (fuente real del proyecto).
     *
     * @return array<int, string>
     */
    private function barrenoProjectNames($samples): array
    {
        return collect($samples)
            ->map(fn ($s) => $s->barreno?->proyecto?->nombre)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Proyecto (E7): proyecto del barreno; si no existe, el de la muestra; si no, 'Media Luna'.
     */
    private function resolveProjectName($samples): string
    {
        $names = $this->barrenoProjectNames($samples);

        if (!empty($names)) {
            return implode(', ', $names);
        }

        return collect($samples)->map(fn ($s) => $s->proyecto?->nombre)->filter()->first() ?? 'Media Luna';
    }

    /**
     * Enviado desde (E8): Coreshack seleccionado en el Monitor (San Miguel / Nuevo Balsas).
     * Si no se ha capturado, hace fallback al proyecto del barreno o de la muestra.
     */
    public function resolveSentFrom(WorkOrder $workOrder, $samples): string
    {
        if (!empty($workOrder->coreshack)) {
            return $workOrder->coreshack;
        }

        $sampleCoreshack = collect($samples)->whereNotNull('coreshack')->first()?->coreshack;
        if (!empty($sampleCoreshack)) {
            return $sampleCoreshack;
        }

        return $this->resolveSentFromProject($samples);
    }

    /**
     * Enviado desde (E8): proyecto real donde está declarado el barreno.
     */
    private function resolveSentFromProject($samples): string
    {
        $names = $this->barrenoProjectNames($samples);

        if (!empty($names)) {
            return implode(', ', $names);
        }

        return collect($samples)->map(fn ($s) => $s->proyecto?->nombre)->filter()->first() ?? 'N/A';
    }

    /**
     * Geólogo (E9): Nombre del geólogo responsable capturado en las muestras de la Work Order.
     * Si no se capturó (registros históricos o sin geólogo), se retorna 'No Capturado'.
     */
    private function resolveGeologistName($samples): string
    {
        $firstWithGeologist = collect($samples)->first(fn ($s) => !empty($s->geologist_id));

        if ($firstWithGeologist && $firstWithGeologist->geologist && !empty($firstWithGeologist->geologist->name)) {
            return $firstWithGeologist->geologist->name;
        }

        return 'No Capturado';
    }

    /**
     * Fecha de Envío (B11): la capturada en el Monitor de Muestreo en formato DD/MM/AAAA.
     * Si no se ha capturado, retorna vacío para permitir captura manual en físico.
     */
    public function resolveSentDate(WorkOrder $workOrder, $samples): string
    {
        $date = collect($samples)->whereNotNull('sent_date')->first()?->sent_date
            ?? $workOrder->dispatch_date;

        return $date ? Carbon::parse($date)->format('d/m/Y') : '';
    }

    /**
     * Escribe la fecha en la celda de fecha de "Firmas de Autorización" (E51).
     * Si no se proporciona fecha ($authorizationDate === null o ''), se deja en blanco
     * para permitir firma y fecha manuscrita física.
     * Localiza dinámicamente la fila: columna E con el rótulo "FECHA"; la celda de captura queda debajo.
     */
    public function writePrintDate($sheet, ?string $authorizationDate = null): void
    {
        if (empty($authorizationDate)) {
            return;
        }

        $highest = $sheet->getHighestRow();
        $labelRow = null;

        for ($row = $highest; $row >= 1; $row--) {
            if (strtoupper(trim((string) $sheet->getCell("E{$row}")->getValue())) === 'FECHA') {
                $labelRow = $row;
                break;
            }
        }

        if ($labelRow === null) {
            // Respaldo: el título "FIRMAS DE AUTORIZACIÓN" está 2 filas arriba de la celda de fecha
            for ($row = $highest; $row >= 1; $row--) {
                if (str_starts_with(strtoupper(trim((string) $sheet->getCell("A{$row}")->getValue())), 'FIRMAS DE AUTORIZ')) {
                    $labelRow = $row + 1;
                    break;
                }
            }
        }

        if ($labelRow !== null) {
            $sheet->setCellValue('E' . ($labelRow + 1), $authorizationDate);
        }
    }

    /**
     * Resuelve la ruta de la plantilla Excel de costales buscando en las ubicaciones soportadas.
     */
    public function resolveTemplatePath(?string $customPath = null): ?string
    {
        if ($customPath !== null) {
            if ($this->isPathReadable($customPath)) {
                $real = realpath($customPath);
                return $real ?: $customPath;
            }
            return null;
        }

        $candidates = [
            resource_path('templates/formatoCostalTemplate.xlsx'),
            storage_path('app/templates/formatoCostalTemplate.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $this->isPathReadable($path)) {
                $real = realpath($path);
                return $real ?: $path;
            }
        }

        return null;
    }

    /**
     * Verifica si un archivo existe y es legible (con fallback a fopen para compatibilidad Windows/PHP).
     */
    private function isPathReadable(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        if (is_readable($path)) {
            return true;
        }

        $fp = @fopen($path, 'rb');
        if ($fp !== false) {
            fclose($fp);
            return true;
        }

        return false;
    }

    /**
     * Agrupa las muestras en costales aplicando las reglas del cliente por diámetro y metraje:
     * - PQ: máx. 2 muestras (3 - 4 m)
     * - HQ: máx. 3 muestras (6 m)
     * - NQ: máx. 4 muestras (8 m)
     * - BQ: máx. 5 muestras (10 m)
     * - Límite global absoluto: máximo 10 muestras por costal
     * - Límite de seguridad laboral: suma de pesos <= $maxWeight (25.0 kg)
     * - Distribución homogénea: reparte equitativamente para evitar costales huérfanos (ej. 11 BQ -> 6 y 5).
     *
     * @param iterable<DrillHoleSample> $samples
     * @return array<int, array{number: int, weight: float, samples: array<string>}>
     */
    public function buildSacks($samples, float $maxWeight = 25.0, float $defaultWeight = 1.0, ?SampleSetting $settings = null): array
    {
        $settings = $settings ?? SampleSetting::getSettings();

        if (empty($samples)) {
            return [];
        }

        $sampleList = is_array($samples)
            ? array_values($samples)
            : (method_exists($samples, 'values') ? $samples->values()->all() : iterator_to_array($samples));

        if (empty($sampleList)) {
            return [];
        }

        // 1. Dividir las muestras en secciones contiguas por diámetro (core_size)
        $sections       = [];
        $currentSection = [];
        $currentDiam    = null;

        foreach ($sampleList as $sample) {
            $diam = $this->resolveCoreSize($sample);

            if ($currentDiam === null || $diam === $currentDiam) {
                $currentSection[] = $sample;
                $currentDiam      = $diam;
            } else {
                $sections[] = [
                    'diameter' => $currentDiam,
                    'samples'  => $currentSection,
                ];
                $currentSection = [$sample];
                $currentDiam    = $diam;
            }
        }

        if (!empty($currentSection)) {
            $sections[] = [
                'diameter' => $currentDiam,
                'samples'  => $currentSection,
            ];
        }

        // 2. Empacar cada sección de forma homogénea y numerar consecutivamente
        $allSacks   = [];
        $sackNumber = 1;

        foreach ($sections as $section) {
            $sectionSacks = $this->partitionSectionHomogeneously(
                $section['samples'],
                $section['diameter'],
                $maxWeight,
                $defaultWeight,
                $settings
            );

            foreach ($sectionSacks as $sack) {
                $sack['number'] = $sackNumber++;
                $allSacks[]     = $sack;
            }
        }

        return $allSacks;
    }

    /**
     * Resuelve el diámetro normalizado de la muestra (PQ, HQ, NQ, BQ).
     */
    public function resolveCoreSize($sample): string
    {
        $coreSize = strtoupper(trim((string) ($sample->core_size ?: $sample->barreno?->core_size)));

        if (str_starts_with($coreSize, 'PQ')) {
            return 'PQ';
        }
        if (str_starts_with($coreSize, 'HQ')) {
            return 'HQ';
        }
        if (str_starts_with($coreSize, 'NQ')) {
            return 'NQ';
        }
        if (str_starts_with($coreSize, 'BQ')) {
            return 'BQ';
        }

        return 'HQ';
    }

    /**
     * Particiona una sección homogénea de muestras según diámetro, metraje y peso.
     *
     * @param array<DrillHoleSample> $samples
     * @return array<int, array{number: int, weight: float, samples: array<string>}>
     */
    private function partitionSectionHomogeneously(
        array $samples,
        string $coreSize,
        float $maxWeight,
        float $defaultWeight,
        SampleSetting $settings
    ): array {
        $rules = match ($coreSize) {
            'PQ' => ['nominal' => 2, 'max_samples' => 2, 'max_meters' => 4.0],
            'HQ' => ['nominal' => 3, 'max_samples' => 3, 'max_meters' => 6.0],
            'NQ' => ['nominal' => 4, 'max_samples' => 5, 'max_meters' => 10.0],
            'BQ' => ['nominal' => 5, 'max_samples' => 6, 'max_meters' => 12.0],
            default => ['nominal' => 3, 'max_samples' => 3, 'max_meters' => 6.0],
        };

        $n = count($samples);
        if ($n === 0) {
            return [];
        }

        // Número inicial de costales: round(N / nominal) para permitir balanceo homogéneo (ej. 11 BQ -> 2 costales de 6 y 5)
        $k = max(1, (int) round($n / $rules['nominal']));

        $finalSacks = [];
        $valid = false;

        while (!$valid && $k <= $n) {
            $candidateSacks = [];
            $base = intdiv($n, $k);
            $rem = $n % $k;
            $sampleIdx = 0;
            $possible = true;

            for ($s = 1; $s <= $k; $s++) {
                $sackCapacity = ($s <= $rem) ? ($base + 1) : $base;

                $sackSampleCodes = [];
                $sackWeight = 0.0;
                $sackMeters = 0.0;

                for ($j = 0; $j < $sackCapacity; $j++) {
                    if ($sampleIdx >= $n) {
                        break;
                    }

                    $sample = $samples[$sampleIdx];
                    $w = $this->resolveSampleWeight($sample, $settings, $defaultWeight);
                    $len = (float) ($sample->sample_length > 0 ? $sample->sample_length : ($sample->length > 0 ? $sample->length : 0));

                    $sackSampleCodes[] = $sample->sample_number;
                    $sackWeight = round($sackWeight + $w, 3);
                    $sackMeters = round($sackMeters + $len, 3);
                    $sampleIdx++;
                }

                // Validar restricciones:
                // 1. Peso acumulado <= max_sack_weight (25.0 kg)
                // 2. Muestras por costal <= 10 (límite global absoluto)
                // 3. Muestras por costal <= max_samples permitido para el diámetro
                // 4. Metraje <= max_meters (con tolerancia de 0.5m)
                $countSamples = count($sackSampleCodes);
                $isWeightSafe = ($sackWeight <= ($maxWeight + 0.0001));
                $isCountSafe  = ($countSamples <= 10 && $countSamples <= $rules['max_samples']);
                $isMetersSafe = ($rules['max_meters'] <= 0 || $sackMeters <= ($rules['max_meters'] + 0.5));

                if (!$isWeightSafe || !$isCountSafe || !$isMetersSafe) {
                    $possible = false;
                    break;
                }

                $candidateSacks[] = [
                    'number'  => $s,
                    'weight'  => $sackWeight,
                    'samples' => $sackSampleCodes,
                ];
            }

            if ($possible && count($candidateSacks) === $k) {
                $valid = true;
                $finalSacks = $candidateSacks;
            } else {
                $k++; // Incrementar y reintentar balanceo
            }
        }

        if (!$valid || empty($finalSacks)) {
            return $this->fallbackGreedySacks($samples, $maxWeight, $defaultWeight, $settings);
        }

        return $finalSacks;
    }

    /**
     * Respaldo voraz acumulativo exclusivamente por peso en casos atípicos extremos.
     *
     * @param array<DrillHoleSample> $samples
     * @return array<int, array{number: int, weight: float, samples: array<string>}>
     */
    private function fallbackGreedySacks(array $samples, float $maxWeight, float $defaultWeight, SampleSetting $settings): array
    {
        $sacks = [];
        $current = ['number' => 1, 'weight' => 0.0, 'samples' => []];

        foreach ($samples as $sample) {
            $w = $this->resolveSampleWeight($sample, $settings, $defaultWeight);

            if (!empty($current['samples']) && (($current['weight'] + $w) > ($maxWeight + 0.0001))) {
                $sacks[] = $current;
                $current = ['number' => count($sacks) + 1, 'weight' => 0.0, 'samples' => []];
            }

            $current['samples'][] = $sample->sample_number;
            $current['weight']    = round($current['weight'] + $w, 3);
        }

        if (!empty($current['samples'])) {
            $sacks[] = $current;
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
     * - Celdas de inicio de nueva serie de folios: Fondo #FCE4D6 (naranja tenue), texto en negrita y borde #ED7D31.
     */
    private function populateSacksGrid($sheet, array $sacks, array $seriesStarts = []): void
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

                        if (!empty($seriesStarts[$sampleCode])) {
                            $this->applyNewSeriesCellStyle($sheet, $dataCell, $dataRow);
                        } else {
                            $this->applySampleCellStyle($sheet, $dataCell, $dataRow);
                        }
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

                        if (!empty($seriesStarts[$sampleCode])) {
                            $this->applyNewSeriesCellStyle($sheet, $dataCell, $dataRow);
                        } else {
                            $this->applySampleCellStyle($sheet, $dataCell, $dataRow);
                        }
                    }
                }

                $currentRow = $headerRow + $totalBlockRows;
            }
        }
    }

    /**
     * Detecta los números de muestra que inician una nueva serie de folios:
     * - Cuando cambia el prefijo alfabético del folio (ej. ML vs NV)
     * - Cuando existe un salto numérico no consecutivo (ej. ML157999 a ML170000)
     *
     * @param iterable<DrillHoleSample> $samples
     * @return array<string, bool> Mapa [sample_number => true]
     */
    public function detectSeriesStarts($samples): array
    {
        $seriesStarts = [];
        $prevPrefix   = null;
        $prevNum      = null;

        $sampleList = is_array($samples)
            ? array_values($samples)
            : (method_exists($samples, 'values') ? $samples->values()->all() : iterator_to_array($samples));

        foreach ($sampleList as $i => $sample) {
            $sampleNumber = trim((string) ($sample->sample_number ?? ''));
            $prefix       = '';
            $numeric      = null;

            if (preg_match('/^([A-Za-z_-]*?)(\d+)$/', $sampleNumber, $matches)) {
                $prefix  = $matches[1];
                $numeric = (int) $matches[2];
            }

            if ($i > 0) {
                $isPrefixChange   = ($prevPrefix !== null && $prefix !== $prevPrefix);
                $isNonConsecutive = ($numeric !== null && $prevNum !== null && $numeric !== ($prevNum + 1));

                if ($isPrefixChange || $isNonConsecutive) {
                    $seriesStarts[$sampleNumber] = true;
                }
            }

            $prevPrefix = $prefix;
            $prevNum    = $numeric;
        }

        return $seriesStarts;
    }

    /**
     * Aplica el estilo requerido para la celda de inicio de nueva serie de folios
     * (Fondo naranja tenue #FCE4D6, texto en negrita #782800 y borde visible #ED7D31) y fija la altura a 20pt.
     */
    public function applyNewSeriesCellStyle($sheet, string $cellCoordinate, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle($cellCoordinate)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFCE4D6'],
            ],
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FF782800'],
                'size'  => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FFED7D31'],
                ],
            ],
        ]);
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
