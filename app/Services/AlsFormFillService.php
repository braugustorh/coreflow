<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlsFormFillService
{
    private const API_URL  = 'https://api.pdf.co/v1/pdf/edit/add';
    private const MAX_ROWS = 7;

    /**
     * Genera y retorna un StreamedResponse de descarga del formulario ALS PDF,
     * rellenando la plantilla oficial de pdf.co mediante la API REST.
     */
    public function generate(WorkOrder $workOrder): StreamedResponse
    {
        // 1. Cargar muestras ordenadas
        $samples = DrillHoleSample::where('work_order_id', $workOrder->id)
            ->with(['proyecto', 'barreno'])
            ->orderByRaw('CAST(sample_number AS UNSIGNED), sample_number')
            ->get();

        if ($samples->isEmpty()) {
            abort(422, 'La Work Order no tiene muestras asignadas.');
        }

        // 2. Datos de cabecera
        $firstSample = $samples->first();
        $proyecto    = $firstSample->proyecto;
        $sede        = $workOrder->sede ?? $proyecto?->sede;
        $companyName = $sede?->name ?? 'Minera Media Luna';

        $supervisor = $samples
            ->whereNotNull('responsible_supervisor')
            ->first()?->responsible_supervisor ?? '';

        $sentDate = $samples
            ->whereNotNull('sent_date')
            ->first()?->sent_date?->format('d/m/Y') ?? now()->format('d/m/Y');

        $isRush       = $samples->contains('rush', true);
        $totalSamples = $workOrder->samples_quantity ?: $samples->count();

        // 3. Construir grupos de rangos
        $groups = $this->buildRangeGroups($samples);

        // 4. Construir el payload de campos para pdf.co
        $fields = $this->buildFields(
            workOrder:    $workOrder,
            companyName:  $companyName,
            supervisor:   $supervisor,
            sentDate:     $sentDate,
            proyecto:     $proyecto,
            isRush:       $isRush,
            totalSamples: $totalSamples,
            groups:       $groups,
        );

        // 5. Llamar a la API de pdf.co
        $pdfContent = $this->callPdfCoApi($fields);

        // 6. Retornar como descarga compatible con Filament/Livewire
        $filename = "Formulario_ALS_WO_{$workOrder->work_order_code}.pdf";

        return response()->streamDownload(
            function () use ($pdfContent) {
                echo $pdfContent;
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Construye el array de campos AcroForm para enviar a pdf.co.
     */
    private function buildFields(
        WorkOrder $workOrder,
        string    $companyName,
        string    $supervisor,
        string    $sentDate,
        mixed     $proyecto,
        bool      $isRush,
        int       $totalSamples,
        array     $groups,
    ): array {
        $fields = [
            // --- Cabecera ---
            ['fieldName' => 'Company name', 'text' => $companyName],
            ['fieldName' => 'Submitted by',  'text' => $supervisor],
            ['fieldName' => 'Project ID',    'text' => $proyecto?->name ?? ''],
            ['fieldName' => 'Dispatch#',     'text' => $workOrder->work_order_code],
            ['fieldName' => 'Workorder#',    'text' => $workOrder->work_order_code],
            ['fieldName' => 'PO#',           'text' => $workOrder->work_order_code],
            ['fieldName' => 'PO #',          'text' => $workOrder->work_order_code],
            ['fieldName' => 'Date',          'text' => $sentDate],
            ['fieldName' => 'MUESTRAS',      'text' => (string) $totalSamples],

            // --- Checkboxes ---
            ['fieldName' => 'Rush',              'text' => $isRush ? 'true' : 'false'],
            ['fieldName' => 'Tipo de muestra',   'text' => 'true'], // siempre Core
        ];

        // --- Filas de la tabla (máximo 7 grupos) ---
        $rows = array_slice($groups, 0, self::MAX_ROWS);

        foreach ($rows as $i => $group) {
            $n = $i + 1; // 1-indexed

            $fields[] = ['fieldName' => "Start#{$n}",    'text' => (string) $group['inicio']];
            $fields[] = ['fieldName' => "Finish#{$n}",   'text' => (string) $group['fin']];
            $fields[] = ['fieldName' => "QTY{$n}",       'text' => (string) $group['cantidad']];
            $fields[] = ['fieldName' => "Type {$n}",     'text' => ''];
        }

        return $fields;
    }

    /**
     * Llama a la API de pdf.co para rellenar la plantilla y retorna el PDF en binario.
     *
     * @throws \RuntimeException si la API responde con error
     */
    private function callPdfCoApi(array $fields): string
    {
        $apiKey   = config('services.pdfco.key');
        $template = config('services.pdfco.template');

        $response = Http::withHeaders([
            'x-api-key'    => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(self::API_URL, [
            'url'     => $template,
            'fields'  => $fields,
            'flatten' => true,   // PDF no editable en el output
            'async'   => false,
            'name'    => 'als_filled.pdf',
        ]);

        if ($response->failed()) {
            Log::error('pdf.co API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            abort(500, 'Error al generar el PDF con pdf.co: ' . $response->status());
        }

        $json = $response->json();

        if (! empty($json['error'])) {
            Log::error('pdf.co API returned error', $json);
            abort(500, 'Error pdf.co: ' . ($json['message'] ?? 'Respuesta inesperada'));
        }

        $pdfUrl = $json['url'] ?? null;

        if (! $pdfUrl) {
            Log::error('pdf.co response missing url', $json);
            abort(500, 'pdf.co no retornó una URL válida del PDF.');
        }

        // Descargar el PDF binario desde la URL temporal (válida 60 min)
        $download = Http::timeout(30)->get($pdfUrl);

        if ($download->failed()) {
            abort(500, 'No se pudo descargar el PDF generado por pdf.co.');
        }

        return $download->body();
    }

    // -------------------------------------------------------------------------
    // Helpers (copiados del AlsFormGeneratorService para mantener misma lógica)
    // -------------------------------------------------------------------------

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

        $groups       = [];
        $currentGroup = null;

        foreach ($samples as $sample) {
            $numericValue = $this->extractNumeric($sample->sample_number);

            if ($currentGroup === null) {
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
                $currentGroup['fin']       = $sample->sample_number;
                $currentGroup['cantidad'] += 1;
                $currentGroup['_last_num'] = $numericValue;
            } else {
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

        if ($currentGroup !== null) {
            unset($currentGroup['_last_num']);
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Extrae el sufijo numérico de un sample_number.
     * Ej: "ML000123" → 123 | "123456" → 123456 | "ABC" → null
     */
    private function extractNumeric(?string $sampleNumber): ?int
    {
        if (empty($sampleNumber)) {
            return null;
        }

        if (is_numeric($sampleNumber)) {
            return (int) $sampleNumber;
        }

        if (preg_match('/(\d+)$/', $sampleNumber, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
