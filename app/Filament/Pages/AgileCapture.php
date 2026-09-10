<?php

namespace App\Filament\Pages;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\StandardSample;
use App\Models\WorkOrder;
use Filament\Actions\BulkAction;
use Filament\Actions\Action as TableAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgileCapture extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Captura Ágil';
    protected static ?string $title = 'Módulo de Captura Ágil';
    protected static string|\UnitEnum|null $navigationGroup = 'Muestreo';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.agile-capture';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS', 'Geologo'])
            || $user->can('View:AgileCapture');
    }

    // Estado Paso 1
    public $searchBarreno = '';
    public ?int $selectedBarrenoId = null;
    public ?DrillHole $barreno = null;
    public bool $isLoadingBarreno = false;

    // Ya no usamos el state de captureData ni getForms, usamos batch desde Alpine.

    public function selectBarreno()
    {
        $this->isLoadingBarreno = true;

        if (!empty($this->searchBarreno)) {
            $this->barreno = DrillHole::where('nombre_barreno', $this->searchBarreno)->first();

            if (!$this->barreno) {
                \Filament\Notifications\Notification::make()
                    ->title('Barreno no encontrado')
                    ->body('El barreno ingresado no existe en los registros.')
                    ->danger()
                    ->actions([
                        \Filament\Actions\Action::make('crear')
                            ->label('Crear Barreno')
                            ->url(\App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('create'))
                            ->button(),
                    ])
                    ->send();

                $this->isLoadingBarreno = false;
                return;
            }

            $this->selectedBarrenoId = $this->barreno->id;

            $lastSample = DrillHoleSample::where('barreno_id', $this->barreno->id)
                ->orderBy('id', 'desc')
                ->first();

            $lastOriginalSample = DrillHoleSample::where('barreno_id', $this->barreno->id)
                ->where('sample_type', 'O')
                ->orderBy('id', 'desc')
                ->first();

            $existingIntervals = DrillHoleSample::where('barreno_id', $this->barreno->id)
                ->where('sample_type', 'O')
                ->whereNotNull('from_depth')
                ->whereNotNull('to_depth')
                ->get(['from_depth', 'to_depth'])
                ->toArray();

            $existingSampleNumbers = DrillHoleSample::where('proyecto_id', $this->barreno->proyecto_id)
                ->pluck('sample_number')
                ->toArray();

            $nextSampleNumber = '';
            if ($lastSample && $lastSample->sample_number) {
                $nextSampleNumber = $lastSample->sample_number;
                $nextSampleNumber++; // Incrementa automáticamente
            }

            // Emitimos evento para que Alpine.js reaccione e inicie el batch
            $this->dispatch('barreno-selected', [
                'selectedBarrenoId' => $this->barreno->id,
                'nextSampleNumber' => $nextSampleNumber,
                'fromDepth' => $lastOriginalSample ? $lastOriginalSample->to_depth : 0,
                'proyectoId' => $this->barreno->proyecto_id,
                'sampledAt' => $lastSample && $lastSample->sampled_at
                    ? $lastSample->sampled_at->format('Y-m-d H:i:s')
                    : now()->format('Y-m-d H:i:s'),
                'existingIntervals' => $existingIntervals,
                'existingSampleNumbers' => $existingSampleNumbers,
                'maxDepth' => $this->barreno->max_depth,
            ]);
        }

        $this->isLoadingBarreno = false;

        // Invalidar cache de la tabla y forzar re-query con el nuevo barreno
        $this->flushCachedTableRecords();
        $this->resetPage();
    }

    public function getProyectosOptionsProperty()
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['super_admin', 'supervisor'])) {
            return Proyecto::select('id', 'nombre')->get();
        }
        return Proyecto::where('sede_id', $user->sede_id)->select('id', 'nombre')->get();
    }

    public function getEstandaresOptionsProperty()
    {
        return StandardSample::where('status', true)->select('id', 'standard_name')->get();
    }

    public function getMuestrasOriginalesOptionsProperty()
    {
        if (!$this->selectedBarrenoId)
            return collect([]);
        return DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
            ->where('sample_type', 'O')
            ->select('id', 'sample_number')
            ->get();
    }

    /**
     * Devuelve el label de blanco apropiado según la sede del usuario autenticado.
     * Media Luna → BLANK-ML, cualquier otra sede → BLANK
     */
    public function getBlankLabelProperty(): string
    {
        $sede = auth()->user()->sede;
        if ($sede && str_contains(strtolower($sede->name), 'media luna')) {
            return 'BLANK-ML';
        }
        return 'BLANK';
    }

    public function saveBatch($samplesData)
    {
        $errors = [];
        $savedCount = 0;
        $maxDepth = $this->barreno?->max_depth;

        // Intervalos para validar traslape (base de datos y lote actual)
        $existingIntervals = \App\Models\DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
            ->where('sample_type', 'O')
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->get(['from_depth', 'to_depth']);

        foreach ($samplesData as $index => $data) {
            $isControl = ($data['sample_type'] ?? 'O') === 'Control';

            // 1. Validaciones de obligatoriedad (no vacíos)
            if (empty($data['proyecto_id'])) {
                $errors[] = "Fila " . ($index + 1) . ": El Proyecto es requerido.";
                continue;
            }
            if (empty($data['sample_number']) || trim((string)$data['sample_number']) === '') {
                $errors[] = "Fila " . ($index + 1) . ": El Número de Muestra es requerido.";
                continue;
            }
            if (empty($data['sampled_at']) || trim((string)$data['sampled_at']) === '') {
                $errors[] = "Fila " . ($index + 1) . ": La Fecha es requerida.";
                continue;
            }

            if ($isControl) {
                if (empty($data['control_type']) || trim((string)$data['control_type']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El Tipo de Control es requerido.";
                    continue;
                }
                if ($data['control_type'] === 'Standard' && empty($data['standard_sample_id'])) {
                    $errors[] = "Fila " . ($index + 1) . ": El Estándar de Referencia es requerido.";
                    continue;
                }
                if ($data['control_type'] === 'Duplicate' && empty($data['duplicate_sample_id'])) {
                    $errors[] = "Fila " . ($index + 1) . ": La Muestra Original duplicada es requerida.";
                    continue;
                }
                $minWeight = (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50);
                $maxWeight = (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00);

                if (isset($data['weight']) && trim((string)$data['weight']) !== '') {
                    $wVal = (float) $data['weight'];
                    if ($wVal < $minWeight || $wVal > $maxWeight) {
                        $errors[] = "Fila " . ($index + 1) . ": El Peso ({$wVal} kg) debe estar entre {$minWeight} kg y {$maxWeight} kg.";
                        continue;
                    }
                }
            } else {
                // Original: from, to, sample_length son requeridos (peso es opcional)
                if (!isset($data['from_depth']) || trim((string)$data['from_depth']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El valor 'Desde (FROM)' es requerido para muestras originales.";
                    continue;
                }
                if (!isset($data['to_depth']) || trim((string)$data['to_depth']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El valor 'Hasta (TO)' es requerido para muestras originales.";
                    continue;
                }
                if (!isset($data['sample_length']) || trim((string)$data['sample_length']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": La recuperación (Sample Length) es requerida para muestras originales.";
                    continue;
                }
                if (isset($data['weight']) && trim((string)$data['weight']) !== '') {
                    $wVal = (float) $data['weight'];
                    $minWeight = (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50);
                    $maxWeight = (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00);
                    if ($wVal < $minWeight || $wVal > $maxWeight) {
                        $errors[] = "Fila " . ($index + 1) . ": El Peso ({$wVal} kg) debe estar entre {$minWeight} kg y {$maxWeight} kg.";
                        continue;
                    }
                }
            }

            // Validar duplicado en el mismo lote
            $duplicateInBatch = false;
            foreach ($samplesData as $checkIndex => $checkData) {
                if ($checkIndex !== $index && $checkData['sample_number'] === $data['sample_number']) {
                    $duplicateInBatch = true;
                    break;
                }
            }
            if ($duplicateInBatch) {
                $errors[] = "Fila " . ($index + 1) . ": Número de muestra duplicado en el lote.";
                continue;
            }

            // Validar duplicado en el proyecto
            $exists = DrillHoleSample::where('proyecto_id', $data['proyecto_id'])
                ->where('sample_number', $data['sample_number'])
                ->exists();

            if ($exists) {
                $errors[] = "Fila " . ($index + 1) . ": Ya existe la muestra \"{$data['sample_number']}\".";
                continue;
            }

            $data['barreno_id'] = $this->selectedBarrenoId;
            $data['user_id'] = auth()->id();
            $data['capture_source'] = 'manual';
            $data['qr_token'] = (string) Str::uuid();

            // Limpiar ID generado por Alpine.js
            unset($data['id']);

            // Convertir strings vacíos a null para campos opcionales
            foreach (['standard_sample_id', 'duplicate_sample_id', 'weight', 'sample_length', 'comentarios', 'from_depth', 'to_depth'] as $field) {
                if (isset($data[$field]) && trim((string)$data[$field]) === '') {
                    $data[$field] = null;
                }
            }

            // Si es Control, forzar intervalos a null
            if ($isControl) {
                $data['from_depth'] = null;
                $data['to_depth'] = null;
                $data['length'] = null;
                $data['sample_length'] = null;

                // Limpiar campos dependiendo del tipo de control
                if (($data['control_type'] ?? '') !== 'Standard') {
                    $data['standard_sample_id'] = null;
                }
                if (($data['control_type'] ?? '') !== 'Duplicate') {
                    $data['duplicate_sample_id'] = null;
                }
            } else {
                // Si es Original, forzar controles a null
                $data['control_type'] = null;
                $data['standard_sample_id'] = null;
                $data['duplicate_sample_id'] = null;

                $fd = (float) $data['from_depth'];
                $td = (float) $data['to_depth'];
                $data['length'] = round($td - $fd, 2);

                if ($fd >= $td) {
                    $errors[] = "Fila " . ($index + 1) . ": El valor Desde ({$fd}) debe ser menor que Hasta ({$td}).";
                    continue;
                }

                // Validación de profundidad máxima (max_depth)
                if ($maxDepth !== null && $maxDepth > 0) {
                    if ($fd > $maxDepth) {
                        $errors[] = "Fila " . ($index + 1) . ": El valor Desde ({$fd}m) no puede superar la profundidad máxima del barreno ({$maxDepth}m).";
                        continue;
                    }
                    if ($td > $maxDepth) {
                        $errors[] = "Fila " . ($index + 1) . ": El valor Hasta ({$td}m) no puede superar la profundidad máxima del barreno ({$maxDepth}m).";
                        continue;
                    }
                }

                // Validar Recuperación vs Longitud
                if (isset($data['sample_length']) && (float) $data['sample_length'] > $data['length']) {
                    $errors[] = "Fila " . ($index + 1) . ": Recuperación mayor a la longitud.";
                    continue;
                }

                // Validar Traslape Backend
                $hasOverlap = false;
                foreach ($existingIntervals as $existing) {
                    if (max($fd, (float) $existing->from_depth) < min($td, (float) $existing->to_depth)) {
                        $hasOverlap = true;
                        break;
                    }
                }

                if ($hasOverlap) {
                    $errors[] = "Fila " . ($index + 1) . ": Traslape detectado en intervalos.";
                    continue;
                }

                // Añadir al pool de intervalos si es válido
                $existingIntervals->push((object) ['from_depth' => $fd, 'to_depth' => $td]);
            }

            DrillHoleSample::create($data);
            $savedCount++;
        }


        if (count($errors) > 0) {
            $errorMsg = implode('<br>', $errors);
            \Filament\Notifications\Notification::make()
                ->title('Errores en el lote')
                ->body($errorMsg)
                ->danger()
                ->persistent()
                ->send();
        }

        if ($savedCount > 0) {
            $this->flushCachedTableRecords();
            $this->resetPage();

            \Filament\Notifications\Notification::make()
                ->title('Lote guardado')
                ->body("Se han guardado {$savedCount} muestras correctamente.")
                ->success()
                ->send();

            $lastSample = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->orderBy('id', 'desc')
                ->first();

            $lastOriginalSample = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->where('sample_type', 'O')
                ->orderBy('id', 'desc')
                ->first();

            $existingIntervals = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->where('sample_type', 'O')
                ->whereNotNull('from_depth')
                ->whereNotNull('to_depth')
                ->get(['from_depth', 'to_depth'])
                ->toArray();

            $proyectoId = DrillHole::find($this->selectedBarrenoId)?->proyecto_id;
            $existingSampleNumbers = DrillHoleSample::where('proyecto_id', $proyectoId)
                ->pluck('sample_number')
                ->toArray();

            $nextSampleNumber = '';
            if ($lastSample && $lastSample->sample_number) {
                $nextSampleNumber = $lastSample->sample_number;
                $nextSampleNumber++;
            }

            // Reiniciar el componente alpine
            $this->dispatch('batch-saved', [
                'nextSampleNumber' => $nextSampleNumber,
                'fromDepth' => $lastOriginalSample ? $lastOriginalSample->to_depth : 0,
                'existingIntervals' => $existingIntervals,
                'existingSampleNumbers' => $existingSampleNumbers,
            ]);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => DrillHoleSample::query()
                    ->with('standardSample')
                    ->where('barreno_id', $this->selectedBarrenoId ?? -1)
                    ->orderBy('sample_number', 'asc')
            )
            ->columns([
                TextColumn::make('sample_number')
                    ->label('Sample Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('from_depth')
                    ->label('From')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('to_depth')
                    ->label('To')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('length')
                    ->label('Drilled Length')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sample_length')
                    ->label('Sample Length')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sample_type')
                    ->label('Sample Type')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('control_type')
                    ->label('Control Type')
                    ->badge()
                    ->color(fn($state) => ($state && str_starts_with($state, 'STD-')) ? 'info' : 'gray')
                    ->formatStateUsing(function ($state, $record) {
                        if (($state === 'Standard' || $state === 'Estándar') && $record->standardSample) {
                            return 'STD-' . $record->standardSample->standard_name;
                        }
                        return $state;
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('core_size')
                    ->label('Core Size')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'PQ' => 'info',
                        'HQ' => 'success',
                        'NQ' => 'warning',
                        'BQ' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextInputColumn::make('weight')
                    ->label('Wght')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn (DrillHoleSample $record): bool => !auth()->user()?->hasRole(['super_admin', 'Admin CoreFlow']) && ($record->is_archived || !empty($record->workOrder?->dispatch_date)))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sampled_at')
                    ->label('Fecha Muestreo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workOrder.work_order_code')
                    ->label('WO')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('comentarios')
                    ->label('Comentarios')
                    ->limit(40)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (DrillHoleSample $record) => "Editar Muestra {$record->sample_number}")
                    ->slideOver()
                    ->disabled(fn (DrillHoleSample $record): bool => !auth()->user()?->hasRole(['super_admin', 'Admin CoreFlow']) && ($record->is_archived || !empty($record->workOrder?->dispatch_date)))
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('from_depth')
                                ->label('Desde (FROM)')
                                ->numeric()
                                ->step(0.01)
                                ->disabled(fn (DrillHoleSample $record) => $record->sample_type === 'Control'),
                            TextInput::make('to_depth')
                                ->label('Hasta (TO)')
                                ->numeric()
                                ->step(0.01)
                                ->disabled(fn (DrillHoleSample $record) => $record->sample_type === 'Control'),
                            TextInput::make('sample_length')
                                ->label('Recuperación (Sample Length)')
                                ->numeric()
                                ->step(0.01)
                                ->disabled(fn (DrillHoleSample $record) => $record->sample_type === 'Control'),
                            TextInput::make('weight')
                                ->label('Peso (kg)')
                                ->numeric()
                                ->step(0.01),
                            Select::make('core_size')
                                ->label('Core Size')
                                ->options([
                                    'PQ' => 'PQ (85.0 mm)',
                                    'HQ' => 'HQ (63.5 mm)',
                                    'NQ' => 'NQ (47.6 mm)',
                                    'BQ' => 'BQ (36.4 mm)',
                                ]),
                            TextInput::make('comentarios')
                                ->label('Comentarios')
                                ->columnSpanFull(),
                        ]),
                    ]),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->disabled(fn (DrillHoleSample $record): bool => !auth()->user()?->hasRole(['super_admin', 'Admin CoreFlow']) && ($record->is_archived || !empty($record->workOrder?->dispatch_date)))
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Muestra')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta muestra? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->headerActions([
                TableAction::make('assignCoreSizeHeader')
                    ->label('Asignar Core Size')
                    ->icon('heroicon-o-circle-stack')
                    ->color('info')
                    ->form(function () {
                        return [
                            Select::make('scope')
                                ->label('Aplicar a')
                                ->options([
                                    'all'    => 'Todas las muestras guardadas del barreno',
                                    'range'  => 'Rango de muestras (Muestra Inicio X a Muestra Fin Y)',
                                    'single' => 'Muestra única',
                                ])
                                ->default('all')
                                ->live(),

                            TextInput::make('sample_start')
                                ->label('Muestra Inicio (X)')
                                ->placeholder('Ej: ML157100')
                                ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                                ->hidden(fn(Get $get) => $get('scope') === 'all'),

                            TextInput::make('sample_end')
                                ->label('Muestra Fin (Y)')
                                ->placeholder('Ej: ML157110')
                                ->required(fn(Get $get) => $get('scope') === 'range')
                                ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                            Select::make('core_size')
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
                        ];
                    })
                    ->modalHeading('Asignar Core Size a Muestras del Barreno')
                    ->action(function (array $data) {
                        $barrenoId = $this->selectedBarrenoId;
                        if (!$barrenoId) return;

                        $query = DrillHoleSample::where('barreno_id', $barrenoId);
                        $scope = $data['scope'] ?? 'all';
                        $coreSize = $data['core_size'];
                        $updatedCount = 0;

                        if ($scope === 'all') {
                            $updatedCount = (clone $query)->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'single') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $updatedCount = (clone $query)
                                ->where('sample_number', $start)
                                ->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'range') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $end   = trim((string) ($data['sample_end'] ?? ''));

                            $allSamples = (clone $query)->get();
                            $matchedIds = $this->getSampleIdsInRange($allSamples, $start, $end);
                            if (!empty($matchedIds)) {
                                $updatedCount = (clone $query)->whereIn('id', $matchedIds)->update(['core_size' => $coreSize]);
                            }
                        }

                        Notification::make()
                            ->title('Core Size asignado')
                            ->body("Core Size {$coreSize} asignado a {$updatedCount} muestra(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('assignCoreSize')
                    ->label('Asignar Core Size')
                    ->icon('heroicon-o-circle-stack')
                    ->color('info')
                    ->form(function (Collection $records) {
                        $sorted = $records->sortBy(function ($s) {
                            if (preg_match('/(\d+)/', (string) $s->sample_number, $m)) {
                                return (int) $m[1];
                            }
                            return $s->sample_number;
                        })->values();

                        $firstNum = $sorted->first()?->sample_number ?? '';
                        $lastNum  = $sorted->last()?->sample_number ?? '';

                        return [
                            Select::make('scope')
                                ->label('Aplicar a')
                                ->options([
                                    'range'  => 'Rango seleccionado (Muestra Inicio a Fin)',
                                    'single' => 'Muestra única',
                                    'all'    => 'Todas las muestras del barreno',
                                ])
                                ->default($sorted->count() > 1 ? 'range' : 'single')
                                ->live(),

                            TextInput::make('sample_start')
                                ->label('Muestra Inicio (X)')
                                ->default($firstNum)
                                ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                                ->hidden(fn(Get $get) => $get('scope') === 'all'),

                            TextInput::make('sample_end')
                                ->label('Muestra Fin (Y)')
                                ->default($lastNum)
                                ->required(fn(Get $get) => $get('scope') === 'range')
                                ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                            Select::make('core_size')
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
                        ];
                    })
                    ->modalHeading('Asignar Core Size masivo')
                    ->modalDescription('Se asignará el Core Size seleccionado a las muestras del barreno.')
                    ->action(function (Collection $records, array $data) {
                        $scope = $data['scope'] ?? 'range';
                        $coreSize = $data['core_size'];
                        $barrenoId = $this->selectedBarrenoId;

                        $query = DrillHoleSample::where('barreno_id', $barrenoId);

                        $updatedCount = 0;
                        if ($scope === 'all') {
                            $updatedCount = (clone $query)->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'single') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $updatedCount = (clone $query)
                                ->where('sample_number', $start)
                                ->update(['core_size' => $coreSize]);
                        } else {
                            $targetIds = $records->pluck('id')->toArray();
                            if (!empty($targetIds)) {
                                $updatedCount = (clone $query)
                                    ->whereIn('id', $targetIds)
                                    ->update(['core_size' => $coreSize]);
                            }
                        }

                        Notification::make()
                            ->title('Core Size asignado')
                            ->body("Core Size {$coreSize} asignado a {$updatedCount} muestra(s) del barreno.")
                            ->success()
                            ->send();
                    }),

                BulkAction::make('assignWorkOrder')
                    ->label('Asignar Work Order')
                    ->icon('heroicon-o-briefcase')
                    ->form(function () {
                        // Obtener sede del barreno actual vía proyecto
                        $sedeId = null;
                        if ($this->selectedBarrenoId) {
                            $sample = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                                ->with('proyecto')
                                ->first();
                            $sedeId = $sample?->proyecto?->sede_id;
                        }

                        return [
                            Select::make('work_order_id')
                                ->label('Work Order')
                                ->options(function () use ($sedeId) {
                                    $query = WorkOrder::query();
                                    if ($sedeId) {
                                        $query->where('sede_id', $sedeId);
                                    }
                                    return $query->get()->mapWithKeys(
                                        fn($wo) =>
                                        [$wo->id => "{$wo->work_order_code} (muestras: {$wo->samples_quantity})"]
                                    );
                                })
                                ->searchable()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('work_order_code')
                                        ->label('Código de nueva Work Order')
                                        ->required()
                                        ->unique('work_orders', 'work_order_code')
                                        ->placeholder('Ej: WO-2026-001'),
                                ])
                                ->createOptionUsing(function (array $data) use ($sedeId) {
                                    $wo = WorkOrder::create([
                                        'work_order_code' => trim($data['work_order_code']),
                                        'sede_id' => $sedeId,
                                        'samples_quantity' => 0,
                                    ]);
                                    return $wo->id;
                                }),
                        ];
                    })
                    ->action(function (Collection $records, array $data) {
                        $barrenoId = $this->selectedBarrenoId;
                        $workOrder = WorkOrder::find($data['work_order_id']);

                        if (!$workOrder) {
                            \Filament\Notifications\Notification::make()
                                ->title('Work Order no encontrada')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Si tiene muestras, verificar que todas sean del mismo barreno
                        if ($workOrder->samples_quantity > 0) {
                            $allSameBarreno = DrillHoleSample::where('work_order_id', $workOrder->id)
                                ->where('barreno_id', '!=', $barrenoId)
                                ->doesntExist();

                            if (!$allSameBarreno) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Work Order no disponible')
                                    ->body("Esta Work Order ya tiene muestras de otros barrenos.")
                                    ->warning()
                                    ->send();
                                return;
                            }
                        }

                        // Validar que todas las muestras seleccionadas tengan Core Size capturado
                        $samplesWithoutCoreSize = $records->filter(function ($sample) {
                            $cs = $sample->core_size ?: $sample->barreno?->core_size;
                            return empty($cs);
                        });

                        if ($samplesWithoutCoreSize->isNotEmpty()) {
                            $count = $samplesWithoutCoreSize->count();
                            $sampleNumbers = $samplesWithoutCoreSize->pluck('sample_number')->take(5)->implode(', ');
                            \Filament\Notifications\Notification::make()
                                ->title('Core Size requerido')
                                ->body("No se puede asignar la Work Order porque {$count} muestra(s) no tienen Core Size asignado ({$sampleNumbers}...). Por favor asigna el Core Size antes de continuar.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Guardar las WOs anteriores para recalcular
                        $previousWoIds = $records->pluck('work_order_id')
                            ->filter()
                            ->unique()
                            ->values();

                        // Asignar la nueva WO a todas las muestras seleccionadas
                        foreach ($records as $record) {
                            $record->update(['work_order_id' => $workOrder->id]);
                        }

                        // Recalcular conteo de WOs anteriores afectadas
                        foreach ($previousWoIds as $prevWoId) {
                            if ($prevWoId === $workOrder->id)
                                continue; // La nueva WO se recalcula abajo
                            $this->recalcularWO($prevWoId);
                        }

                        // Recalcular conteo de la WO nueva/seleccionada
                        $this->recalcularWO($workOrder->id);

                        $this->resetPage();

                        \Filament\Notifications\Notification::make()
                            ->title('Work Order asignada')
                            ->body("La orden {$workOrder->work_order_code} fue asignada a {$records->count()} muestras.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Recalcula el samples_quantity de una Work Order.
     */
    private function recalcularWO(int $woId): void
    {
        $wo = WorkOrder::find($woId);
        if ($wo) {
            $wo->samples_quantity = DrillHoleSample::where('work_order_id', $woId)->count();
            $wo->save();
        }
    }

    protected function generateZpl($records)
    {
        $zplCodes = [];
        foreach ($records as $record) {
            $woCode = $record->workOrder ? $record->workOrder->work_order_code : 'N/A';
            $proyecto = $record->proyecto ? $record->proyecto->nombre : 'N/A';
            $zplCodes[] = "^XA\n^FO50,50^A0N,50,50^FDProyecto: {$proyecto}^FS\n^FO50,120^A0N,50,50^FDWO: {$woCode}^FS\n^FO50,190^A0N,50,50^FDMuestra: {$record->sample_number}^FS\n^FO50,260^BQN,2,5^FDQA,{$record->qr_token}^FS\n^XZ";
        }

        $allZpl = implode("\n", $zplCodes);

        return response()->streamDownload(function () use ($allZpl) {
            echo $allZpl;
        }, 'etiquetas_' . time() . '.zpl');
    }

    // Calculators for Widgets
    public function getTotalMuestrasProperty(): int
    {
        return $this->selectedBarrenoId ? DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)->count() : 0;
    }

    public function getTotalEstandaresProperty(): int
    {
        return $this->selectedBarrenoId ? DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)->where('control_type', 'Standard')->count() : 0;
    }

    public function getTotalBlancosProperty(): int
    {
        // Cubre BLANK, BLANK-ML, BLANK-SN, etc.
        return $this->selectedBarrenoId ? DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)->where('control_type', 'like', 'BLANK%')->count() : 0;
    }

    public function getTotalDuplicadosProperty(): int
    {
        return $this->selectedBarrenoId ? DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)->where('control_type', 'Duplicate')->count() : 0;
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
