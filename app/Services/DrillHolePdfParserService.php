<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\HttpFoundation\File\File;

class DrillHolePdfParserService
{
    /**
     * Extrae información técnica y espacial desde un archivo PDF de reporte de levantamiento.
     *
     * @param mixed $file Objeto UploadedFile, ruta relativa (storage) o ruta absoluta.
     * @return array Estructura con datos de atributos planeados, instalados y metadatos.
     */
    public function parsePdf(mixed $file): array
    {
        $fullPath = $this->resolveFilePath($file);

        if (!$fullPath || !file_exists($fullPath)) {
            Log::warning("PDF parser: Archivo no encontrado o no resuelto: " . json_encode($file));
            return [];
        }

        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($fullPath);
            $text = $pdf->getText();
        } catch (Exception $e) {
            Log::error("Error parseando PDF en DrillHolePdfParserService: " . $e->getMessage());
            return [];
        }

        return $this->extractDataFromText($text);
    }

    /**
     * Resuelve la ruta física del archivo según el tipo de entrada recibida.
     */
    private function resolveFilePath(mixed $file): ?string
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if ($file instanceof File || $file instanceof UploadedFile) {
            return $file->getRealPath();
        }

        if (is_string($file) && !empty($file)) {
            if (file_exists($file)) {
                return $file;
            }
            if (Storage::disk('public')->exists($file)) {
                return Storage::disk('public')->path($file);
            }
            if (Storage::exists($file)) {
                return Storage::path($file);
            }
        }

        return null;
    }

    /**
     * Procesa el texto plano del PDF para extraer atributos.
     */
    public function extractDataFromText(string $text): array
    {
        $result = [
            'planned' => [
                'easting'   => null,
                'northing'  => null,
                'elevation' => null,
                'dip'       => null,
                'azimuth'   => null,
            ],
            'installed' => [
                'easting'   => null,
                'northing'  => null,
                'elevation' => null,
                'dip'       => null,
                'azimuth'   => null,
            ],
            'planilla'    => null,
            'barreno_id'  => null,
            'responsible' => null,
            'survey_date' => null,
        ];

        // 1. Extraer Responsable
        if (preg_match('/RESPONSABLE:\s*([^\r\n]+)/i', $text, $matches)) {
            $result['responsible'] = trim($matches[1]);
        }

        // 2. Extraer Fecha
        if (preg_match('/FECHA:\s*([^\r\n]+)/i', $text, $matches)) {
            $rawDate = trim($matches[1]);
            $result['survey_date'] = $this->parseSpanishDate($rawDate);
        }

        // 3. Extraer Bloque ATRIBUTOS PLANEADOS
        if (preg_match('/ATRIBUTOS\s+PLANEADOS([\s\S]*?)(ATRIBUTOS\s+INSTALADOS|NOTA:)/i', $text, $blockMatches)) {
            $plannedBlock = $blockMatches[1];
            $plannedData = $this->parseTableBlock($plannedBlock);

            if ($plannedData) {
                $result['planilla']             = $plannedData['planilla'] ?? null;
                $result['barreno_id']           = $plannedData['barreno_id'] ?? null;
                $result['planned']['easting']   = $plannedData['easting'] ?? null;
                $result['planned']['northing']  = $plannedData['northing'] ?? null;
                $result['planned']['elevation'] = $plannedData['elevation'] ?? null;
                $result['planned']['dip']       = $plannedData['dip'] ?? null;
                $result['planned']['azimuth']   = $plannedData['azimuth'] ?? null;
            }
        }

        // 4. Extraer Bloque ATRIBUTOS INSTALADOS
        if (preg_match('/ATRIBUTOS\s+INSTALADOS([\s\S]*?)(NOTA:|INFORMACI[OÓ]N\s+DE\s+VERIFICACI[OÓ]N|REGISTRO|$)/i', $text, $blockMatches)) {
            $installedBlock = $blockMatches[1];
            $installedData = $this->parseTableBlock($installedBlock);

            if ($installedData) {
                if (empty($result['planilla'])) {
                    $result['planilla'] = $installedData['planilla'] ?? null;
                }
                if (empty($result['barreno_id'])) {
                    $result['barreno_id'] = $installedData['barreno_id'] ?? null;
                }
                $result['installed']['easting']   = $installedData['easting'] ?? null;
                $result['installed']['northing']  = $installedData['northing'] ?? null;
                $result['installed']['elevation'] = $installedData['elevation'] ?? null;
                $result['installed']['dip']       = $installedData['dip'] ?? null;
                $result['installed']['azimuth']   = $installedData['azimuth'] ?? null;
            }
        }

        return $result;
    }

    /**
     * Parsea un bloque de texto que contiene una tabla de atributos.
     */
    private function parseTableBlock(string $textBlock): ?array
    {
        // Limpiar cabeceras conocidas
        $cleanedText = preg_replace('/PLANILLA|ID|ESTE|NORTE|ELEVACION|DIP|AZIMUTH/i', '', $textBlock);
        
        // Tokenizar por espacios y saltos de línea
        $tokens = preg_split('/\s+/', trim($cleanedText));
        $tokens = array_values(array_filter($tokens, fn($t) => strlen(trim($t)) > 0));

        if (empty($tokens)) {
            return null;
        }

        $parsed = [
            'planilla'   => null,
            'barreno_id' => null,
            'easting'    => null,
            'northing'   => null,
            'elevation'  => null,
            'dip'        => null,
            'azimuth'    => null,
        ];

        // 1. Intento por regex directa de fila completa
        $fullRegex = '/([A-Z0-9\-_]+)\s+([A-Z0-9\-_]+)\s+([\d,]+\.?\d*)\s+([\d,]+\.?\d*)\s+([\d,]+\.?\d*)\s+(-?[\d\.]+)\s*°?\s+([\d\.]+)\s*°?/i';
        if (preg_match($fullRegex, $textBlock, $m)) {
            return [
                'planilla'   => trim($m[1]),
                'barreno_id' => trim($m[2]),
                'easting'    => $this->cleanNumber($m[3]),
                'northing'   => $this->cleanNumber($m[4]),
                'elevation'  => $this->cleanNumber($m[5]),
                'dip'        => $this->cleanNumber($m[6]),
                'azimuth'    => $this->cleanNumber($m[7]),
            ];
        }

        // 2. Intento secuencial por lista de tokens
        $numbers = [];

        foreach ($tokens as $token) {
            $tokenClean = trim(str_replace(['°', 'º'], '', $token));

            // Si coincide con Planilla (ej. MLE-04)
            if (preg_match('/^[A-Z]{2,}-\d+$/i', $tokenClean) && empty($parsed['planilla'])) {
                $parsed['planilla'] = $tokenClean;
                continue;
            }

            // Si coincide con ID Barreno (ej. MLE26-039, DDH-001)
            if (preg_match('/^[A-Z0-9\-_]{4,}$/i', $tokenClean) && empty($parsed['barreno_id']) && $tokenClean !== $parsed['planilla']) {
                $parsed['barreno_id'] = $tokenClean;
                continue;
            }

            // Si es numérico (o número con formato)
            $numVal = str_replace(',', '', $tokenClean);
            if (is_numeric($numVal)) {
                $numbers[] = (float) $numVal;
            }
        }

        // Si encontramos al menos 5 valores numéricos en la tabla (Este, Norte, Elevación, Dip, Azimut)
        if (count($numbers) >= 5) {
            $parsed['easting']   = $numbers[0];
            $parsed['northing']  = $numbers[1];
            $parsed['elevation'] = $numbers[2];
            $parsed['dip']       = $numbers[3];
            $parsed['azimuth']   = $numbers[4];
        }

        if ($parsed['easting'] || $parsed['northing'] || $parsed['planilla']) {
            return $parsed;
        }

        return null;
    }

    /**
     * Remueve comas de separadores de miles ("423,282.288" -> "423282.288").
     */
    private function cleanNumber(string $value): float
    {
        $cleaned = str_replace(',', '', trim($value));
        return (float) $cleaned;
    }

    /**
     * Parsea fechas en formato español (ej. "14 DE JULIO 2026").
     */
    private function parseSpanishDate(string $rawDate): ?string
    {
        $months = [
            'ENERO'      => '01',
            'FEBRERO'    => '02',
            'MARZO'      => '03',
            'ABRIL'      => '04',
            'MAYO'       => '05',
            'JUNIO'      => '06',
            'JULIO'      => '07',
            'AGOSTO'     => '08',
            'SEPTIEMBRE' => '09',
            'OCTUBRE'    => '10',
            'NOVIEMBRE'  => '11',
            'DICIEMBRE'  => '12',
        ];

        $upperDate = mb_strtoupper(trim($rawDate));

        foreach ($months as $name => $num) {
            if (str_contains($upperDate, $name)) {
                if (preg_match('/(\d{1,2})\s+DE\s+[A-Z]+\s+(\d{4})/i', $upperDate, $m)) {
                    $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                    $year = $m[2];
                    return "{$year}-{$num}-{$day}";
                }
            }
        }

        try {
            return Carbon::parse($rawDate)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}
