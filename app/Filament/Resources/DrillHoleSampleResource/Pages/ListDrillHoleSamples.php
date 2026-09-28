<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Pages;

use App\Filament\Resources\DrillHoleSampleResource;
use App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Services\SampleValidationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;

class ListDrillHoleSamples extends ListRecords
{
    protected static string $resource = DrillHoleSampleResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            DrillHoleSampleStats::class,
        ];
    }

    /**
     * Determina si un valor de control_type del CSV corresponde a un Blanco.
     * Cubre: BLANK, BLANCO, BLK, BCO, BLANK-ML, BLANK-SN, BLANK-algo, etc.
     */
    protected function isBlankControlType(string $value): bool
    {
        $upper = strtoupper(trim($value));

        if (in_array($upper, ['BLANK', 'BLANCO', 'BLK', 'BCO'])) {
            return true;
        }

        if (str_starts_with($upper, 'BLANK-')) {
            return true;
        }

        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_csv')
                ->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Archivo CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),

                    DateTimePicker::make('sampled_at')
                        ->label('Fecha de Muestreo')
                        ->required()
                        ->default(now())
                        ->helperText('Fecha y hora en que se realizó el muestreo de este lote.'),
                ])
                ->action(function (array $data, SampleValidationService $service) {
                    $path = Storage::disk('local')->path($data['csv_file']);

                    if (!file_exists($path) || !is_readable($path)) {
                        Notification::make()->title('Error al leer el archivo.')->danger()->send();
                        return;
                    }

                    $file   = fopen($path, 'r');
                    $header = fgetcsv($file);

                    if (!$header) {
                        Notification::make()->title('Archivo vacío o incorrecto.')->danger()->send();
                        return;
                    }

                    // BOM removal y normalización de encabezados
                    $header[0]         = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                    $normalizedHeaders = array_map(fn($h) => strtolower(trim($h)), $header);

                    // Leer todas las filas en memoria
                    $rows = [];
                    while (($row = fgetcsv($file)) !== false) {
                        if (array_filter($row) === []) {
                            continue;
                        }
                        $rows[] = array_combine($normalizedHeaders, array_pad($row, count($normalizedHeaders), null));
                    }
                    fclose($file);

                    if (empty($rows)) {
                        Notification::make()->title('El CSV no contiene datos.')->danger()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 1: Columna bhid presente ─────────────────────────
                    $bhidList = collect($rows)->pluck('bhid')->filter()->unique()->values()->toArray();
                    if (empty($bhidList)) {
                        Notification::make()->title('El CSV no contiene la columna BHID o está vacía.')->danger()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 2: Barrenos existen en BD ────────────────────────
                    $existingBarrenos = \App\Models\DrillHole::whereIn('nombre_barreno', $bhidList)
                        ->get()->keyBy('nombre_barreno');
                    $missingBarrenos = array_diff($bhidList, $existingBarrenos->keys()->toArray());

                    if (count($missingBarrenos) > 0) {
                        Notification::make()
                            ->title('Error: Barrenos no encontrados en el sistema')
                            ->body('Los siguientes barrenos no existen. Crea primero los barrenos: ' . implode(', ', $missingBarrenos))
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 3: Barrenos tienen proyecto_id ───────────────────
                    $barrenosSinProyecto = $existingBarrenos
                        ->filter(fn($b) => is_null($b->proyecto_id))
                        ->pluck('nombre_barreno')->toArray();

                    if (count($barrenosSinProyecto) > 0) {
                        Notification::make()
                            ->title('Error: Barrenos sin proyecto asignado')
                            ->body('Edita estos barrenos y asígnales un proyecto primero: ' . implode(', ', $barrenosSinProyecto))
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 4: Sin muestras previas en esos barrenos ─────────
                    $barrenoIds          = $existingBarrenos->pluck('id')->toArray();
                    $barrenosConMuestras = DrillHoleSample::whereIn('barreno_id', $barrenoIds)
                        ->select('barreno_id')->distinct()->pluck('barreno_id')->toArray();

                    if (count($barrenosConMuestras) > 0) {
                        $nombres = $existingBarrenos->whereIn('id', $barrenosConMuestras)->pluck('nombre_barreno')->toArray();
                        Notification::make()
                            ->title('Error: Muestras ya capturadas')
                            ->body('Ya existen muestras para: ' . implode(', ', $nombres))
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 5: Estándares referenciados existen en catálogo ──
                    // Mapa de sample_numbers que vienen en el CSV (para detección de duplicados)
                    $csvSampleNumbers = collect($rows)->pluck('sample_number')->filter()->flip()->toArray();

                    $standardNamesInCsv = [];
                    foreach ($rows as $row) {
                        $sampleType  = strtoupper(trim($row['sample_type'] ?? ''));
                        $controlType = trim($row['control_type'] ?? '');

                        if (empty($controlType) || $sampleType !== 'CONTROL') {
                            continue;
                        }
                        if ($this->isBlankControlType($controlType)) {
                            continue;
                        }
                        // Si coincide con un sample_number del CSV → es duplicado, no estándar
                        if (isset($csvSampleNumbers[$controlType])) {
                            continue;
                        }

                        $standardNamesInCsv[] = $controlType;
                    }
                    $standardNamesInCsv = array_unique($standardNamesInCsv);

                    $missingStandards = [];
                    $standardsByName  = [];
                    if (!empty($standardNamesInCsv)) {
                        $foundStandards  = StandardSample::whereIn('standard_name', $standardNamesInCsv)->get();
                        $standardsByName = $foundStandards->keyBy('standard_name')->toArray();
                        $missingStandards = array_diff($standardNamesInCsv, array_keys($standardsByName));
                    }

                    if (count($missingStandards) > 0) {
                        Notification::make()
                            ->title('Error: Estándares no encontrados en el catálogo')
                            ->body('Da de alta primero estos estándares en el sistema: ' . implode(', ', $missingStandards))
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── INSERCIÓN ───────────────────────────────────────────────────
                    // Se insertan primero los originales, luego los controles.
                    // Esto garantiza que cuando se inserte un Duplicate, el ID de la muestra
                    // original (que puede venir en el mismo CSV) ya esté disponible.

                    $userId    = auth()->id();
                    $sampledAt = $data['sampled_at'];

                    // Mapa: sample_number → ID insertado en BD
                    $insertedSampleIds = [];
                    $importedCount     = 0;

                    $originalRows = [];
                    $controlRows  = [];

                    foreach ($rows as $row) {
                        if (strtoupper(trim($row['sample_type'] ?? 'O')) === 'CONTROL') {
                            $controlRows[] = $row;
                        } else {
                            $originalRows[] = $row;
                        }
                    }

                    // Helper: construye el payload base de una muestra
                    $buildBase = function (array $row) use ($existingBarrenos, $userId, $sampledAt): array {
                        $barreno = $existingBarrenos[$row['bhid']];

                        // ── Función auxiliar: busca el primer alias encontrado ──────────
                        $col = function (array $aliases) use ($row): ?float {
                            foreach ($aliases as $alias) {
                                if (isset($row[$alias]) && trim((string) $row[$alias]) !== '') {
                                    return (float) $row[$alias];
                                }
                            }
                            return null;
                        };

                        $colStr = function (array $aliases) use ($row): ?string {
                            foreach ($aliases as $alias) {
                                if (isset($row[$alias]) && trim((string) $row[$alias]) !== '') {
                                    return trim($row[$alias]);
                                }
                            }
                            return null;
                        };

                        // Mapeo tolerante a variantes de nombre de columna CSV:
                        // from_depth   → from, from_depth
                        // to_depth     → to, to_depth
                        // length       → drilled_length, drilled_len, length
                        // sample_length→ sample_length, samp_len, recovery
                        // weight       → wght, weight, peso
                        // comments     → comments, comment, comentarios
                        $fromDepth  = $col(['from',           'from_depth']);
                        $toDepth    = $col(['to',             'to_depth']);
                        $drilledLen = $col(['drilled_length', 'drilled_len', 'length']);
                        $sampleLen  = $col(['sample_length',  'samp_len',   'recovery']);
                        $weight     = $col(['wght',           'weight',     'peso']);
                        $comments   = $colStr(['comments',   'comment',    'comentarios']);

                        // Si el CSV tiene FROM y TO pero no DRILLED_LENGTH, calcularlo
                        if ($drilledLen === null && $fromDepth !== null && $toDepth !== null && $toDepth > $fromDepth) {
                            $drilledLen = round($toDepth - $fromDepth, 3);
                        }

                        return [
                            'user_id'                => $userId,
                            'editor_id'              => null,
                            'capture_source'         => 'import',
                            'status'                 => 'draft',
                            'errors'                 => null,
                            'barreno_id'             => $barreno->id,
                            'proyecto_id'            => $barreno->proyecto_id,
                            'work_order_id'          => null,
                            'qr_token'               => null,
                            'from_depth'             => $fromDepth,
                            'to_depth'               => $toDepth,
                            'length'                 => $drilledLen,
                            'sample_length'          => $sampleLen,
                            'sample_number'          => $row['sample_number'] ?? null,
                            'sample_type'            => $row['sample_type']   ?? 'O',
                            'weight'                 => $weight,
                            'comentarios'            => $comments,
                            'sampled_at'             => $sampledAt,
                            'core_size'              => null,
                            'hole_status'            => null,
                            'sent_date'              => null,
                            'sampling_assistant'     => null,
                            'bags'                   => null,
                            'sacks'                  => null,
                            'responsible_supervisor' => null,
                            'bags_sacks_status'      => null,
                            'is_archived'            => false,
                            'control_type'           => null,
                            'standard_sample_id'     => null,
                            'duplicate_sample_id'    => null,
                        ];
                    };


                    // Paso A — Insertar originales (O / Composite)
                    foreach ($originalRows as $row) {
                        $payload = $buildBase($row);
                        $sample  = DrillHoleSample::create($payload);

                        if (!empty($row['sample_number'])) {
                            $insertedSampleIds[$row['sample_number']] = $sample->id;
                        }
                        $importedCount++;
                    }

                    // Paso B — Insertar controles resolviendo tipo
                    foreach ($controlRows as $row) {
                        $payload    = $buildBase($row);
                        $controlRaw = trim($row['control_type'] ?? '');

                        // Los controles no tienen profundidades
                        $payload['from_depth']    = null;
                        $payload['to_depth']      = null;
                        $payload['length']        = null;
                        $payload['sample_length'] = null;

                        if ($this->isBlankControlType($controlRaw)) {
                            // ── BLANCO: conservar el valor tal cual viene del CSV (ej. BLANK-ML)
                            $payload['control_type'] = strtoupper(trim($controlRaw));

                        } elseif (isset($csvSampleNumbers[$controlRaw])) {
                            // ── DUPLICADO: el control_type coincide con un sample_number del CSV
                            $payload['control_type']        = 'Duplicate';
                            $payload['duplicate_sample_id'] = $insertedSampleIds[$controlRaw] ?? null;

                        } else {
                            // ── ESTÁNDAR: buscar en catálogo
                            $payload['control_type']      = 'Standard';
                            $payload['standard_sample_id'] = $standardsByName[$controlRaw]['id'] ?? null;
                        }

                        DrillHoleSample::create($payload);
                        $importedCount++;
                    }

                    Notification::make()
                        ->title("{$importedCount} muestras importadas correctamente.")
                        ->success()
                        ->send();

                    // ── Validación automática inmediata ─────────────────
                    // Se corre sin esperar que el usuario pulse "Validar"
                    $service->validateDraftsForUser($userId);

                    $errorsCount = DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->whereNotNull('errors')
                        ->count();

                    if ($errorsCount > 0) {
                        Notification::make()
                            ->title("{$errorsCount} registros con problemas detectados")
                            ->body('Revisa los registros marcados en rojo antes de oficializar.')
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Validación completada — sin errores detectados.')
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('validate_data')
                ->label('Validar Datos')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->action(function (SampleValidationService $service) {
                    $userId = auth()->id();
                    $service->validateDraftsForUser($userId);

                    $errorsCount = DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->whereNotNull('errors')
                        ->count();

                    if ($errorsCount > 0) {
                        Notification::make()
                            ->title("{$errorsCount} registros con observaciones detectadas")
                            ->body('Revisa los registros marcados en rojo. Si incluyen GAPs sin validar, el Administrador y Supervisor Coreshack han sido notificados automáticamente.')
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Validación completada — sin errores detectados.')
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('officialize_data')
                ->label('Acreditar / Oficializar')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (SampleValidationService $service) {
                    $userId = auth()->id();

                    // Re-validar antes de intentar oficializar para asegurar que las notificaciones de GAP se emitan
                    $service->validateDraftsForUser($userId);

                    $baseQuery = DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import');

                    $totalDrafts = (clone $baseQuery)->count();
                    if ($totalDrafts === 0) {
                        Notification::make()
                            ->title('No hay registros para oficializar.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $draftsWithErrors = (clone $baseQuery)->whereNotNull('errors')->count();
                    $draftsWithoutWorkOrder = (clone $baseQuery)->whereNull('work_order_id')->count();
                    $draftsWithoutCoreSize = (clone $baseQuery)->whereNull('core_size')->count();

                    if ($draftsWithErrors > 0 || $draftsWithoutWorkOrder > 0 || $draftsWithoutCoreSize > 0) {
                        $messages = [];
                        $hasGapError = false;

                        if ($draftsWithErrors > 0) {
                            $messages[] = "• {$draftsWithErrors} registros con errores pendientes por corregir.";
                            $sampleWithErrors = (clone $baseQuery)->whereNotNull('errors')->first();
                            if ($sampleWithErrors && is_array($sampleWithErrors->errors)) {
                                $errorStr = implode(' ', $sampleWithErrors->errors);
                                if (str_contains($errorStr, 'GAP')) {
                                    $hasGapError = true;
                                }
                            }
                        }
                        if ($draftsWithoutWorkOrder > 0) {
                            $messages[] = "• {$draftsWithoutWorkOrder} muestras sin Orden de Trabajo (Work Order) asignada.";
                        }
                        if ($draftsWithoutCoreSize > 0) {
                            $messages[] = "• {$draftsWithoutCoreSize} muestras sin Core Size (Tamaño de Núcleo) asignado.";
                        }

                        Notification::make()
                            ->title('No se puede oficializar')
                            ->body("Corrige los siguientes problemas antes de proceder:\n" . implode("\n", $messages))
                            ->danger()
                            ->persistent()
                            ->send();

                        if ($hasGapError) {
                            Notification::make()
                                ->title('Solicitud de Validación Enviada')
                                ->body('Se ha enviado una notificación al super_admin, Administrador y Supervisor Coreshack para validar el tramo sin información en Barrenos.')
                                ->info()
                                ->send();
                        }

                        return;
                    }

                    DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->update(['status' => 'official']);

                    Notification::make()
                        ->title('Los datos han sido acreditados oficialmente.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('assign_core_size_header')
                ->label('Asignar Core Size')
                ->icon('heroicon-o-circle-stack')
                ->color('info')
                ->form([
                    Forms\Components\Radio::make('scope')
                        ->label('Aplicar a')
                        ->options([
                            'all'    => 'Todas las muestras importadas del barreno',
                            'range'  => 'Rango de muestras (Muestra Inicio X a Muestra Fin Y)',
                            'single' => 'Muestra única',
                        ])
                        ->default('range')
                        ->live(),

                    Forms\Components\TextInput::make('sample_start')
                        ->label('Muestra Inicio (X)')
                        ->placeholder('Ej: ML157100')
                        ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                        ->hidden(fn(Get $get) => $get('scope') === 'all'),

                    Forms\Components\TextInput::make('sample_end')
                        ->label('Muestra Fin (Y)')
                        ->placeholder('Ej: ML157110')
                        ->required(fn(Get $get) => $get('scope') === 'range')
                        ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                    Forms\Components\Select::make('core_size')
                        ->label('Tamaño de Núcleo (Core Size)')
                        ->options([
                            'BQ'  => 'BQ  (36.5 mm)',
                            'NQ'  => 'NQ  (47.6 mm)',
                            'NQ2' => 'NQ2 (50.5 mm)',
                            'HQ'  => 'HQ  (63.5 mm)',
                            'HQ3' => 'HQ3 (61.1 mm)',
                            'PQ'  => 'PQ  (83.0 mm)',
                            'N/A' => 'N/A (No Aplica)',
                        ])
                        ->required()
                        ->native(false),
                ])
                ->modalHeading('Asignar Core Size masivo')
                ->modalDescription('Selecciona las muestras y el Core Size a aplicar.')
                ->action(function (array $data, SampleValidationService $service) {
                    $userId = auth()->id();
                    $baseQuery = DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->where('is_archived', false)
                        ->whereDoesntHave('workOrder', fn($q) => $q->whereHas('drillHoleSamples', fn($s) => $s->where('is_archived', true)));

                    $scope     = $data['scope'] ?? 'all';
                    $coreSize  = $data['core_size'];
                    $updatedCount = 0;

                    if ($scope === 'all') {
                        $updatedCount = (clone $baseQuery)->update(['core_size' => $coreSize]);
                    } elseif ($scope === 'single') {
                        $sampleStart = trim((string) ($data['sample_start'] ?? ''));
                        $updatedCount = (clone $baseQuery)
                            ->where('sample_number', $sampleStart)
                            ->update(['core_size' => $coreSize]);
                    } elseif ($scope === 'range') {
                        $sampleStart = trim((string) ($data['sample_start'] ?? ''));
                        $sampleEnd   = trim((string) ($data['sample_end'] ?? ''));

                        $draftSamples = (clone $baseQuery)->get();
                        $matchedIds   = $this->getSampleIdsInRange($draftSamples, $sampleStart, $sampleEnd);

                        if (!empty($matchedIds)) {
                            $updatedCount = DrillHoleSample::whereIn('id', $matchedIds)
                                ->update(['core_size' => $coreSize]);
                        }
                    }

                    // Re-validar los borradores del usuario
                    $service->validateDraftsForUser($userId);

                    Notification::make()
                        ->title("Core Size {$coreSize} asignado a {$updatedCount} muestra(s).")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getSampleIdsInRange($samples, string $startStr, string $endStr): array
    {
        $startStr = strtoupper(trim($startStr));
        $endStr   = strtoupper(trim($endStr));

        if ($startStr === $endStr) {
            return $samples->filter(fn($s) => strtoupper(trim((string) $s->sample_number)) === $startStr)
                ->pluck('id')->toArray();
        }

        $extractNum = function ($str) {
            if (preg_match('/(\d+)/', $str, $matches)) {
                return (int) $matches[1];
            }
            return null;
        };

        $numStart = $extractNum($startStr);
        $numEnd   = $extractNum($endStr);

        if ($numStart !== null && $numEnd !== null && $numStart > $numEnd) {
            $tmp = $numStart;
            $numStart = $numEnd;
            $numEnd = $tmp;
        }

        $matched = [];
        $inRange = false;

        foreach ($samples as $sample) {
            $numStr = strtoupper(trim((string) $sample->sample_number));
            $numVal = $extractNum($numStr);

            if ($numStart !== null && $numEnd !== null && $numVal !== null) {
                if ($numVal >= $numStart && $numVal <= $numEnd) {
                    $matched[] = $sample->id;
                }
            } else {
                if ($numStr === $startStr || $numStr === $endStr) {
                    $inRange = !$inRange;
                    $matched[] = $sample->id;
                    continue;
                }
                if ($inRange) {
                    $matched[] = $sample->id;
                }
            }
        }

        return $matched;
    }
}
