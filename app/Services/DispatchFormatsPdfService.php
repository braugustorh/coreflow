<?php

namespace App\Services;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispatchFormatsPdfService
{
    private const PDFCO_UPLOAD_BASE64_URL = 'https://api.pdf.co/v1/file/upload/base64';
    private const PDFCO_MERGE_URL = 'https://api.pdf.co/v1/pdf/merge';

    public function __construct(
        private readonly AlsFormFillService $alsFormFillService,
        private readonly SackFormGeneratorService $sackFormGeneratorService,
    ) {
    }

    /**
     * Genera y retorna un StreamedResponse con el Expediente Completo en PDF unificado
     * (Página 1: Formulario ALS + Páginas 2..N: Registro de Costales).
     *
     * @throws RuntimeException si la Work Order no tiene muestras o falla la generación de algún documento
     */
    public function generateUnifiedResponse(WorkOrder $workOrder): StreamedResponse
    {
        $unifiedBinary = $this->generateUnifiedBinary($workOrder);
        $filename = "Formatos_WO_{$workOrder->work_order_code}.pdf";

        return response()->streamDownload(
            function () use ($unifiedBinary) {
                echo $unifiedBinary;
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Genera ambos documentos en formato PDF y los combina en un único binario PDF.
     *
     * @throws RuntimeException si la Work Order no tiene muestras o falla algún servicio
     */
    public function generateUnifiedBinary(WorkOrder $workOrder): string
    {
        // Validación temprana previa a consumir servicios externos (pdf.co)
        if (! $workOrder->drillHoleSamples()->exists()) {
            throw new RuntimeException('La Work Order no tiene muestras asignadas.', 422);
        }

        $alsBinary = $this->alsFormFillService->generatePdfBinary($workOrder);
        $sacksBinary = $this->sackFormGeneratorService->generatePdfBinary($workOrder);

        // FPDI descarta anotaciones /Annots (campos AcroForm sin aplanar).
        // Si el PDF aún contiene widgets/campos interactivos, delegar la unión a pdf.co.
        if ($this->hasInteractiveFormFields($alsBinary)) {
            Log::warning('El PDF de ALS contiene campos AcroForm sin aplanar; usando respaldo pdf.co merge.', [
                'work_order_id' => $workOrder->id,
            ]);

            return $this->mergeViaPdfCoFallback($alsBinary, $sacksBinary);
        }

        try {
            return $this->mergeWithFpdi($alsBinary, $sacksBinary);
        } catch (PdfParserException $e) {
            Log::warning('FPDI no pudo parsear un PDF externo (posible compresión XRef >= 1.5); usando respaldo pdf.co merge.', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            return $this->mergeViaPdfCoFallback($alsBinary, $sacksBinary);
        }
    }

    /**
     * Detecta si un binario PDF contiene campos de formulario interactivos (/Widget o /FT)
     * que se perderían al importar la página como XObject estático en FPDI.
     */
    public function hasInteractiveFormFields(string $pdfBinary): bool
    {
        return preg_match('/\/Subtype\s*\/Widget\b/', $pdfBinary) === 1
            || preg_match('/\/FT\s*\/(Tx|Btn|Ch|Sig)\b/', $pdfBinary) === 1;
    }

    /**
     * Combina múltiples binarios PDF en memoria preservando tamaño y orientación de cada página.
     */
    public function mergeWithFpdi(string ...$pdfBinaries): string
    {
        $fpdi = new Fpdi();
        $fpdi->SetAutoPageBreak(false);

        foreach ($pdfBinaries as $binary) {
            $this->appendPdfPages($fpdi, $binary);
        }

        return $fpdi->Output('S');
    }

    /**
     * Importa todas las páginas de un binario PDF dentro de la instancia FPDI.
     */
    private function appendPdfPages(Fpdi $fpdi, string $pdfBinary): void
    {
        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfBinary));

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $fpdi->importPage($pageNo);
            $size = $fpdi->getTemplateSize($templateId);

            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($templateId);
        }
    }

    /**
     * Respaldo mediante API pdf.co (/v1/pdf/merge) en caso de que el PDF externo utilice
     * compresión XRef no soportada por el parser gratuito de FPDI.
     */
    private function mergeViaPdfCoFallback(string $alsBinary, string $sacksBinary): string
    {
        $apiKey = (string) config('services.pdfco.key');
        if ($apiKey === '') {
            throw new RuntimeException('No fue posible unificar los PDFs localmente ni se dispone de clave pdf.co.', 500);
        }

        $alsUrl = $this->uploadPdfBase64ToPdfCo($apiKey, $alsBinary, 'als_part.pdf');
        $sacksUrl = $this->uploadPdfBase64ToPdfCo($apiKey, $sacksBinary, 'sacks_part.pdf');

        $mergeResponse = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
            ->connectTimeout(15)
            ->timeout(60)
            ->post(self::PDFCO_MERGE_URL, [
                'url' => "{$alsUrl},{$sacksUrl}",
                'async' => false,
                'name' => 'formats_unified.pdf',
            ]);

        if ($mergeResponse->failed() || ! empty($mergeResponse->json('error'))) {
            throw new RuntimeException('Error al unificar los PDFs con pdf.co.', 500);
        }

        $mergedUrl = $mergeResponse->json('url');
        if (! $mergedUrl) {
            throw new RuntimeException('pdf.co no retornó una URL válida al unificar los PDFs.', 500);
        }

        $download = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(3, 1000)
            ->get($mergedUrl);

        if ($download->failed()) {
            throw new RuntimeException('No se pudo descargar el PDF unificado desde pdf.co.', 500);
        }

        return $download->body();
    }

    private function uploadPdfBase64ToPdfCo(string $apiKey, string $binary, string $filename): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
            ->connectTimeout(15)
            ->timeout(60)
            ->post(self::PDFCO_UPLOAD_BASE64_URL, [
                'file' => base64_encode($binary),
                'name' => $filename,
            ]);

        $url = $response->json('url');
        if ($response->failed() || ! empty($response->json('error')) || ! $url) {
            throw new RuntimeException("Error al subir {$filename} a pdf.co para unificación.", 500);
        }

        return $url;
    }
}
