<?php

namespace App\Filament\Resources\HistoricalSamples\Pages;

use App\Filament\Resources\HistoricalSamples\HistoricalSampleResource;
use App\Models\DrillHoleSample;
use App\Models\HistoricalSample;
use App\Models\Proyecto;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListHistoricalSamples extends ListRecords
{
    protected static string $resource = HistoricalSampleResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label('Descargar Plantilla')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(function () {
                        // UTF-8 BOM para que Excel en Windows lo abra con acentos correctos
                        echo "\xEF\xBB\xBF";
                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['numero_muestra', 'proyecto', 'barreno']);
                        fputcsv($output, ['ML-10001', 'Media Luna East', 'DDH-001']);
                        fputcsv($output, ['ML-10002', '', '']);
                        fputcsv($output, ['ML-10003', 'Media Luna West', 'DDH-002']);
                        fclose($output);
                    }, 'plantilla_muestras_historicas.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Action::make('import_historical_samples')
                ->label('Cargar Muestras Históricas')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(function (): bool {
                    $user = auth()->user();
                    if (!$user || $user->hasRole('Visor')) {
                        return false;
                    }
                    return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor Coreshack', 'Geologo'])
                        || $user->can('Create:HistoricalSample');
                })
                ->modalHeading('Importación Masiva de Muestras Históricas')
                ->modalDescription('Cargue un archivo CSV o Excel con los folios de muestras históricas. El número de muestra es obligatorio; proyecto y barreno son opcionales.')
                ->modalSubmitActionLabel('Procesar e Importar')
                ->form([
                    FileUpload::make('file')
                        ->label('Archivo CSV o Excel (.xlsx, .csv)')
                        ->disk('local')
                        ->directory('imports/historical_samples')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),

                    Select::make('default_proyecto_id')
                        ->label('Proyecto por Defecto (Opcional)')
                        ->helperText('Se asignará a las filas del archivo donde la columna proyecto venga vacía.')
                        ->relationship('proyecto', 'nombre')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);
                    if (!file_exists($filePath) || !is_readable($filePath)) {
                        Notification::make()->title('No se pudo leer el archivo cargado.')->danger()->send();
                        return;
                    }

                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $rawRows = [];

                    if (in_array($ext, ['xlsx', 'xls'])) {
                        $spreadsheet = IOFactory::load($filePath);
                        $sheet = $spreadsheet->getActiveSheet();
                        $sheetData = $sheet->toArray(null, true, true, false);

                        if (!empty($sheetData)) {
                            $header = array_shift($sheetData);
                            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
                            foreach ($sheetData as $row) {
                                if (array_filter($row) === []) continue;
                                $rawRows[] = array_combine($header, array_pad($row, count($header), null));
                            }
                        }
                    } else {
                        // Modo CSV
                        $handle = fopen($filePath, 'r');
                        $header = fgetcsv($handle);
                        if ($header) {
                            $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                            // Detectar si el delimitador fue punto y coma ';'
                            if (count($header) === 1 && str_contains($header[0], ';')) {
                                rewind($handle);
                                $header = fgetcsv($handle, 0, ';');
                                $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                                $delimiter = ';';
                            } else {
                                $delimiter = ',';
                            }
                            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

                            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                                if (array_filter($row) === []) continue;
                                $rawRows[] = array_combine($header, array_pad($row, count($header), null));
                            }
                        }
                        fclose($handle);
                    }

                    if (empty($rawRows)) {
                        Notification::make()->title('El archivo no contiene filas de datos.')->warning()->send();
                        return;
                    }

                    // Mapeo de proyectos en memoria para resolución rápida
                    $proyectosMap = Proyecto::all()->keyBy(fn($p) => mb_strtolower(trim($p->nombre)));
                    $defaultProyectoId = $data['default_proyecto_id'] ?? null;

                    $sampleKey = null;
                    foreach (['numero_muestra', 'sample_number', 'muestra', 'sample'] as $candidate) {
                        if (array_key_exists($candidate, $rawRows[0] ?? [])) {
                            $sampleKey = $candidate;
                            break;
                        }
                    }

                    if (!$sampleKey) {
                        Notification::make()
                            ->title('Formato incorrecto')
                            ->body('El archivo debe incluir la columna "numero_muestra" o "sample_number".')
                            ->danger()
                            ->send();
                        return;
                    }

                    $proyectoKey = null;
                    foreach (['proyecto', 'project', 'proyecto_nombre'] as $candidate) {
                        if (array_key_exists($candidate, $rawRows[0] ?? [])) {
                            $proyectoKey = $candidate;
                            break;
                        }
                    }

                    $barrenoKey = null;
                    foreach (['barreno', 'drill_hole', 'bhid', 'nombre_barreno'] as $candidate) {
                        if (array_key_exists($candidate, $rawRows[0] ?? [])) {
                            $barrenoKey = $candidate;
                            break;
                        }
                    }

                    $validRecords = [];
                    $batchSampleKeys = [];
                    $duplicateInBatchCount = 0;
                    $emptyCount = 0;

                    foreach ($rawRows as $row) {
                        $sampleNumber = trim((string)($row[$sampleKey] ?? ''));
                        if ($sampleNumber === '') {
                            $emptyCount++;
                            continue;
                        }

                        // Resolver proyecto_id
                        $proyectoId = $defaultProyectoId;
                        if ($proyectoKey && !empty($row[$proyectoKey])) {
                            $projName = mb_strtolower(trim((string)$row[$proyectoKey]));
                            if (isset($proyectosMap[$projName])) {
                                $proyectoId = $proyectosMap[$projName]->id;
                            } elseif (is_numeric($row[$proyectoKey]) && Proyecto::where('id', $row[$proyectoKey])->exists()) {
                                $proyectoId = (int)$row[$proyectoKey];
                            }
                        }

                        $barrenoName = $barrenoKey ? trim((string)($row[$barrenoKey] ?? '')) : null;
                        if ($barrenoName === '') {
                            $barrenoName = null;
                        }

                        // Unicidad dentro del lote
                        $comboKey = ($proyectoId ?? 'global') . '|' . mb_strtoupper($sampleNumber);
                        if (isset($batchSampleKeys[$comboKey])) {
                            $duplicateInBatchCount++;
                            continue;
                        }
                        $batchSampleKeys[$comboKey] = true;

                        $validRecords[] = [
                            'sample_number' => $sampleNumber,
                            'proyecto_id'   => $proyectoId,
                            'barreno_name'  => $barrenoName,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }

                    if (empty($validRecords)) {
                        Notification::make()
                            ->title('No se encontraron muestras válidas para importar.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // Descartar los que ya existan en la BD (en historical_samples o drill_hole_samples)
                    $importedCount = 0;
                    $alreadyExistsCount = 0;
                    $recordsToInsert = [];

                    // Agrupar por proyecto para optimizar la consulta de existencia
                    $groupedByProject = collect($validRecords)->groupBy(fn($r) => $r['proyecto_id'] ?? 0);

                    foreach ($groupedByProject as $projId => $items) {
                        $pId = $projId === 0 ? null : (int)$projId;
                        $sampleNumbers = $items->pluck('sample_number')->toArray();

                        // Buscar en historical_samples
                        $existingInHist = HistoricalSample::whereIn('sample_number', $sampleNumbers)
                            ->when($pId, fn($q) => $q->where(fn($sub) => $sub->where('proyecto_id', $pId)->orWhereNull('proyecto_id')))
                            ->pluck('sample_number')
                            ->map(fn($s) => mb_strtoupper($s))
                            ->toArray();

                        // Buscar en drill_hole_samples
                        $existingInDhs = DrillHoleSample::whereIn('sample_number', $sampleNumbers)
                            ->when($pId, fn($q) => $q->where('proyecto_id', $pId))
                            ->pluck('sample_number')
                            ->map(fn($s) => mb_strtoupper($s))
                            ->toArray();

                        $alreadyInDb = array_flip(array_merge($existingInHist, $existingInDhs));

                        foreach ($items as $rec) {
                            $upper = mb_strtoupper($rec['sample_number']);
                            if (isset($alreadyInDb[$upper])) {
                                $alreadyExistsCount++;
                            } else {
                                $recordsToInsert[] = $rec;
                            }
                        }
                    }

                    // Inserción en bloques de 1,000 registros
                    if (!empty($recordsToInsert)) {
                        DB::transaction(function () use ($recordsToInsert, &$importedCount) {
                            foreach (array_chunk($recordsToInsert, 1000) as $chunk) {
                                HistoricalSample::insert($chunk);
                                $importedCount += count($chunk);
                            }
                        });
                    }

                    Notification::make()
                        ->title("Importación finalizada con éxito")
                        ->body("Se importaron **{$importedCount}** folios históricos. " .
                            ($alreadyExistsCount > 0 ? "Se omitieron **{$alreadyExistsCount}** duplicados ya existentes en el sistema. " : '') .
                            ($duplicateInBatchCount > 0 ? "Se omitieron **{$duplicateInBatchCount}** repetidos en el archivo." : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
