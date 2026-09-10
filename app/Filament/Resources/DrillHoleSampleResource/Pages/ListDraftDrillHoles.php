<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Pages;

use App\Filament\Resources\DrillHoleSampleResource;
use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Services\SampleValidationService;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Enums\Width;
use App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListDraftDrillHoles extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = DrillHoleSampleResource::class;

    protected static ?string $title = 'Bandeja de Barrenos en Borrador (Validación de CSV)';

    protected string $view = 'filament.resources.drill-hole-sample-resource.pages.list-draft-drill-holes';

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    /**
     * Determina si un valor de control_type del CSV corresponde a un Blanco.
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
                    $existingBarrenos = DrillHole::with(['sede', 'proyecto'])
                        ->whereIn('nombre_barreno', $bhidList)
                        ->get()->keyBy('nombre_barreno');
                    $missingBarrenos = array_diff($bhidList, $existingBarrenos->keys()->toArray());

                    if (count($missingBarrenos) > 0) {
                        Notification::make()
                            ->title('Error: Barrenos no encontrados en el sistema')
                            ->body('Los siguientes barrenos no existen. Crea primero los barrenos: ' . implode(', ', $missingBarrenos))
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 2.1: Control de Tenancy por Distrito Minero (Sede) ──
                    $user = auth()->user();
                    $isAdmin = $user->hasRole(['super_admin', 'Admin CoreFlow']) || $user->id === 1;

                    if (!$isAdmin && $user->sede_id) {
                        $barrenosOtraSede = $existingBarrenos->filter(fn($b) => (int)$b->sede_id !== (int)$user->sede_id);
                        if ($barrenosOtraSede->isNotEmpty()) {
                            $nombres = $barrenosOtraSede->pluck('nombre_barreno')->implode(', ');
                            $miSede = $user->sede?->name ?? 'Distrito asignado';
                            Notification::make()
                                ->title('Error de Seguridad: Barrenos de otro Distrito')
                                ->body("No tienes permisos para importar barrenos de otro distrito minero. Los siguientes barrenos no corresponden a tu sede ({$miSede}): {$nombres}.")
                                ->danger()->persistent()->send();
                            return;
                        }
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
                            ->body('Ya existen muestras registradas para: ' . implode(', ', $nombres) . '. Para evitar inconsistencias, no se puede duplicar la carga.')
                            ->danger()->persistent()->send();
                        return;
                    }

                    // ─── VALIDACIÓN 5: Estándares referenciados existen en catálogo ──
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
                    $userId    = auth()->id();
                    $sampledAt = $data['sampled_at'];
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

                    $buildBase = function (array $row) use ($existingBarrenos, $userId, $sampledAt): array {
                        $barreno = $existingBarrenos[$row['bhid']];

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

                        $fromDepth  = $col(['from',           'from_depth']);
                        $toDepth    = $col(['to',             'to_depth']);
                        $drilledLen = $col(['drilled_length', 'drilled_len', 'length']);
                        $sampleLen  = $col(['sample_length',  'samp_len',   'recovery']);
                        $weight     = $col(['wght',           'weight',     'peso']);
                        $comments   = $colStr(['comments',   'comment',    'comentarios']);

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

                    // Paso A: Insertar originales
                    foreach ($originalRows as $row) {
                        $payload = $buildBase($row);
                        $payload['sample_type'] = 'O';
                        $sample  = DrillHoleSample::create($payload);

                        if (!empty($row['sample_number'])) {
                            $insertedSampleIds[$row['sample_number']] = $sample->id;
                        }
                        $importedCount++;
                    }

                    // Paso B: Insertar controles
                    foreach ($controlRows as $row) {
                        $payload    = $buildBase($row);
                        $payload['sample_type'] = 'Control';
                        $controlRaw = trim($row['control_type'] ?? '');

                        $payload['from_depth']    = null;
                        $payload['to_depth']      = null;
                        $payload['length']        = null;
                        $payload['sample_length'] = null;

                        if ($this->isBlankControlType($controlRaw)) {
                            $payload['control_type'] = 'Blank-ML';
                        } elseif (isset($csvSampleNumbers[$controlRaw])) {
                            $payload['control_type']        = 'Duplicate';
                            $payload['duplicate_sample_id'] = $insertedSampleIds[$controlRaw] ?? null;
                        } else {
                            $payload['control_type']       = 'Standard';
                            $payload['standard_sample_id'] = $standardsByName[$controlRaw]['id'] ?? null;
                        }

                        DrillHoleSample::create($payload);
                        $importedCount++;
                    }

                    // ── Validación automática inmediata por barreno ───────
                    foreach ($barrenoIds as $bId) {
                        $service->validateDraftsForBarreno($bId);
                    }

                    Notification::make()
                        ->title("{$importedCount} muestras importadas correctamente en la bandeja.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['super_admin', 'Admin CoreFlow']) || $user->id === 1;
        $isSupervisor = $user->hasRole(['Supervisor CoreS', 'Supervisor Coreshack', 'supervisor', 'Supervisor']);

        return $table
            ->query(function () use ($user, $isAdmin, $isSupervisor) {
                $query = DrillHole::query()
                    ->whereHas('drillHoleSamples', fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import'))
                    ->with(['sede', 'proyecto'])
                    ->withCount([
                        'drillHoleSamples as draft_samples_count' => fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import'),
                        'drillHoleSamples as errors_count' => fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import')->whereNotNull('errors'),
                        'drillHoleSamples as missing_wo_count' => fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import')->whereNull('work_order_id'),
                        'drillHoleSamples as missing_cs_count' => fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import')->whereNull('core_size'),
                    ]);

                if ($isAdmin) {
                    return $query;
                }

                if ($isSupervisor) {
                    return $query->where('sede_id', $user->sede_id);
                }

                // Geólogo / Capturista: Ve barrenos de su sede donde él cargó muestras en borrador
                return $query->where('sede_id', $user->sede_id)
                    ->whereHas('drillHoleSamples', fn(Builder $q) => $q->where('status', 'draft')->where('capture_source', 'import')->where('user_id', $user->id));
            })
            ->columns([
                Tables\Columns\TextColumn::make('sede.name')
                    ->label('Distrito Minero')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('proyecto.nombre')
                    ->label('Proyecto')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre_barreno')
                    ->label('Barreno (BHID)')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('draft_uploader_name')
                    ->label('Responsable de Carga')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('draft_samples_count')
                    ->label('Muestras')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('quality_status')
                    ->label('Estado de Calidad')
                    ->badge()
                    ->state(function (DrillHole $record): string {
                        if ($record->errors_count > 0) {
                            return "{$record->errors_count} con Errores";
                        }
                        if ($record->missing_wo_count > 0 && $record->missing_cs_count > 0) {
                            return 'Falta WO y Core Size';
                        }
                        if ($record->missing_wo_count > 0) {
                            return 'Falta Work Order';
                        }
                        if ($record->missing_cs_count > 0) {
                            return 'Falta Core Size';
                        }
                        return 'Listo para Oficializar';
                    })
                    ->color(function (DrillHole $record): string {
                        if ($record->errors_count > 0) {
                            return 'danger';
                        }
                        if ($record->missing_wo_count > 0 || $record->missing_cs_count > 0) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->icon(function (DrillHole $record): string {
                        if ($record->errors_count > 0) {
                            return 'heroicon-o-exclamation-circle';
                        }
                        if ($record->missing_wo_count > 0 || $record->missing_cs_count > 0) {
                            return 'heroicon-o-clock';
                        }
                        return 'heroicon-o-check-badge';
                    }),

                Tables\Columns\TextColumn::make('draft_sampled_at')
                    ->label('Fecha de Carga')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sede_id')
                    ->label('Distrito Minero')
                    ->relationship('sede', 'name')
                    ->visible(fn() => $isAdmin),

                Tables\Filters\SelectFilter::make('proyecto_id')
                    ->label('Proyecto')
                    ->relationship('proyecto', 'nombre'),

                Tables\Filters\Filter::make('with_errors')
                    ->label('Solo con errores')
                    ->query(fn(Builder $query) => $query->whereHas('drillHoleSamples', fn($q) => $q->where('status', 'draft')->where('capture_source', 'import')->whereNotNull('errors'))),

                Tables\Filters\Filter::make('ready_to_officialize')
                    ->label('Solo listos para oficializar')
                    ->query(fn(Builder $query) => $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('status', 'draft')->where('capture_source', 'import')->where(fn($sub) => $sub->whereNotNull('errors')->orWhereNull('work_order_id')->orWhereNull('core_size')))),
            ])
            ->recordActions([
                Actions\Action::make('review')
                    ->label('Revisar Muestras')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn(DrillHole $record): string => DrillHoleSampleResource::getUrl('review', ['record' => $record->id])),

                Actions\Action::make('officialize')
                    ->label('Oficializar')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn(DrillHole $record) => "Oficializar Barreno {$record->nombre_barreno}")
                    ->modalDescription('¿Confirmas que deseas acreditar oficialmente todas las muestras de este barreno? Pasarán a estatus oficial y se cerrará este borrador.')
                    ->visible(fn(DrillHole $record) => (int)$record->errors_count === 0 && (int)$record->missing_wo_count === 0 && (int)$record->missing_cs_count === 0)
                    ->action(function(DrillHole $record, SampleValidationService $service) {
                        $service->validateDraftsForBarreno($record->id);

                        $hasInconsistencies = DrillHoleSample::where('barreno_id', $record->id)
                            ->where('status', 'draft')
                            ->where('capture_source', 'import')
                            ->where(function($q) {
                                $q->whereNotNull('errors')
                                  ->orWhereNull('work_order_id')
                                  ->orWhereNull('core_size');
                            })
                            ->exists();

                        if ($hasInconsistencies) {
                            Notification::make()
                                ->title('No se puede oficializar')
                                ->body('El barreno aún tiene inconsistencias, errores o faltan datos requeridos.')
                                ->danger()
                                ->send();
                            return;
                        }

                        DrillHoleSample::where('barreno_id', $record->id)
                            ->where('status', 'draft')
                            ->where('capture_source', 'import')
                            ->update(['status' => 'official']);

                        Notification::make()
                            ->title("Barreno {$record->nombre_barreno} acreditado oficialmente.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('discard')
                    ->label('Descartar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn(DrillHole $record) => "Descartar Borrador de {$record->nombre_barreno}")
                    ->modalDescription('¿Estás seguro de descartar las muestras en borrador de este barreno? Esta acción eliminará los registros de este borrador.')
                    ->action(function(DrillHole $record) {
                        DrillHoleSample::where('barreno_id', $record->id)
                            ->where('status', 'draft')
                            ->where('capture_source', 'import')
                            ->delete();

                        Notification::make()
                            ->title("Borrador del barreno {$record->nombre_barreno} descartado.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No hay barrenos en borrador')
            ->emptyStateDescription('Todos los muestreos importados han sido oficializados o aún no se ha subido un archivo CSV.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
