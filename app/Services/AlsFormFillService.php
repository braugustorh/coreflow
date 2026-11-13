<?php

namespace App\Services;

use App\Models\DrillHoleSample;
use App\Models\User;
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
        try {
            $pdfContent = $this->generatePdfBinary($workOrder);
        } catch (\RuntimeException $e) {
            $status = in_array($e->getCode(), [422, 500], true) ? $e->getCode() : 500;
            abort($status, $e->getMessage());
        }

        // Retornar como descarga compatible con Filament/Livewire
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
     * Genera y retorna el binario PDF crudo del formulario ALS para la Work Order.
     *
     * @throws \RuntimeException si la Work Order no tiene muestras o falla la API pdf.co
     */
    public function generatePdfBinary(WorkOrder $workOrder): string
    {
        // 1. Cargar muestras ordenadas
        $samples = DrillHoleSample::where('work_order_id', $workOrder->id)
            ->with(['proyecto', 'barreno.proyecto'])
            ->orderByRaw('CAST(sample_number AS UNSIGNED), sample_number')
            ->get();

        if ($samples->isEmpty()) {
            throw new \RuntimeException('La Work Order no tiene muestras asignadas.', 422);
        }

        // 2. Datos de cabecera
        $companyName = (string) config('als.company', 'Minera Media Luna');

        // Código de supervisor elegido en el monitor (MF / EC)
        $supervisorCode = trim((string) ($samples
            ->whereNotNull('responsible_supervisor')
            ->first()?->responsible_supervisor ?? ''));

        // Nombre del usuario cuyo code coincide; vacío si no existe
        $supervisor = $supervisorCode !== ''
            ? (string) (User::where('code', $supervisorCode)->value('name') ?? '')
            : '';

        $phone = (string) (config('als.supervisor_phones')[strtoupper($supervisorCode)] ?? '');

        // Fecha de envío capturada (sin respaldo a la fecha actual)
        $sentDate = $samples
            ->whereNotNull('sent_date')
            ->first()?->sent_date?->format('d/m/Y')
            ?? $workOrder->dispatch_date?->format('d/m/Y')
            ?? '';

        // Fecha de recepción del laboratorio (vacía si el laboratorio aún no ha recibido la orden)
        $receptionDate = $workOrder->reception_date?->format('d/m/Y') ?? '';

        // Proyecto tomado del barreno (primero las muestras no QC)
        $isControl = fn ($s) => strtolower((string) $s->sample_type) === 'control' || ! empty($s->control_type);
        $proyecto = $samples->reject($isControl)->map(fn ($s) => $s->barreno?->proyecto)->filter()->first()
            ?? $samples->map(fn ($s) => $s->barreno?->proyecto)->filter()->first();

        $projectName = $proyecto?->nombre ?: (string) config('als.unregistered_label', 'Sin registrar');
        $blankGra09  = $proyecto !== null
            && in_array(strtoupper((string) $proyecto->code), config('als.blank_gra09_projects', []), true);

        $isRush       = $samples->contains('rush', true);
        $totalSamples = $samples->count();

        // 3. Construir grupos de rangos
        $groups = $this->buildRangeGroups($samples);

        // 4. Construir el payload de campos para pdf.co
        $fields = $this->buildFields(
            workOrder:     $workOrder,
            companyName:   $companyName,
            supervisor:    $supervisor,
            phone:         $phone,
            sentDate:      $sentDate,
            receptionDate: $receptionDate,
            projectName:   $projectName,
            blankGra09:    $blankGra09,
            isRush:        $isRush,
            totalSamples:  $totalSamples,
            groups:        $groups,
        );

        // 5. Llamar a la API de pdf.co
        return $this->callPdfCoApi($fields);
    }

    /**
     * Construye el array de campos AcroForm para enviar a pdf.co.
     */
    private function buildFields(
        WorkOrder $workOrder,
        string    $companyName,
        string    $supervisor,
        string    $phone,
        string    $sentDate,
        string    $receptionDate,
        string    $projectName,
        bool      $blankGra09,
        bool      $isRush,
        int       $totalSamples,
        array     $groups,
    ): array {
        $fields = [
            // --- Cabecera ---
            ['fieldName' => 'Company name',      'text' => $companyName],
            ['fieldName' => 'Client Code',       'text' => ''], // Limpiar 'XXX' predefinido en la plantilla
            ['fieldName' => 'Submitted by',      'text' => $supervisor],
            ['fieldName' => 'Contact number',    'text' => $phone],
            ['fieldName' => 'Autorised by Name', 'text' => $supervisor],
            ['fieldName' => 'Project ID',        'text' => $projectName],
            ['fieldName' => 'Dispatch#',         'text' => $workOrder->work_order_code],
            ['fieldName' => 'Workorder#',        'text' => ''], // Orden de trabajo del laboratorio (dejar en blanco)
            ['fieldName' => 'PO#',               'text' => $workOrder->work_order_code],
            ['fieldName' => 'PO #',              'text' => $workOrder->work_order_code],
            ['fieldName' => 'Date',              'text' => $receptionDate], // Fecha de recepción en laboratorio
            ['fieldName' => 'date shipped',      'text' => $sentDate], // Fecha de envío
            ['fieldName' => 'MUESTRAS',          'text' => (string) $totalSamples],

            // --- Checkboxes ---
            ['fieldName' => 'Rush',              'text' => $isRush ? 'true' : 'false'],
            ['fieldName' => 'Tipo de muestra',   'text' => 'true'], // siempre Core
        ];

        // --- Columna Análisis: en ATZ/NJO/NAR se vacía la celda 7 (OA-GRA09s) ---
        if ($blankGra09) {
            $fields[] = ['fieldName' => 'Analytical 7', 'text' => ''];
        }

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
        ])
        ->withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])
        ->connectTimeout(15)
        ->timeout(60)
        ->post(self::API_URL, [
            'url'      => $template,
            'fields'   => $fields,
            'profiles' => "{ 'FlattenDocument()': [] }", // Aplana AcroForm a contenido estático no editable
            'async'    => false,
            'name'     => 'als_filled.pdf',
        ]);

        if ($response->failed()) {
            Log::error('pdf.co API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Error al generar el PDF con pdf.co: ' . $response->status(), 500);
        }

        $json = $response->json();

        if (! empty($json['error'])) {
            Log::error('pdf.co API returned error', $json);
            throw new \RuntimeException('Error pdf.co: ' . ($json['message'] ?? 'Respuesta inesperada'), 500);
        }

        $pdfUrl = $json['url'] ?? null;

        if (! $pdfUrl) {
            Log::error('pdf.co response missing url', $json);
            throw new \RuntimeException('pdf.co no retornó una URL válida del PDF.', 500);
        }

        // Descargar el PDF binario desde la URL temporal (válida 60 min)
        // Se fuerza IPv4 y reintentos para evitar cuelgues de resolución DNS (IPv6) en entornos locales Windows/Laragon
        $download = Http::withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])
        ->connectTimeout(15)
        ->timeout(60)
        ->retry(3, 1000)
        ->get($pdfUrl);

        if ($download->failed()) {
            throw new \RuntimeException('No se pudo descargar el PDF generado por pdf.co.', 500);
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
