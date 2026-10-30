<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrillHoleSampleResource\Pages;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Models\WorkOrder;
use App\Services\SampleExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class DrillHoleSampleResource extends Resource
{
    protected static ?string $model = DrillHoleSample::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Validación de CSV';

    protected static ?string $modelLabel = 'Muestra';

    protected static ?string $pluralModelLabel = 'Muestras';

    protected static string|\UnitEnum|null $navigationGroup = 'Muestreo';

    protected static ?int $navigationSort = 2;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components(static::getSampleFormSchema());
    }

    public static function getSampleFormSchema(): array
    {
        return [
            // ── Sección: Intervalo ─────────────────────────────────────
            Section::make('Intervalo')
                ->description(fn(?DrillHoleSample $record) => $record?->isSent() ? '🔒 Esta muestra está bloqueada: la Work Order ya fue enviada al laboratorio.' : null)
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sample_number')
                        ->label('No. Muestra')
                        ->required()
                        ->maxLength(50)
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->live(onBlur: true)
                        ->rules([
                            fn (Get $get, ?DrillHoleSample $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                $proyectoId = $record?->proyecto_id 
                                    ?? $record?->barreno?->proyecto_id 
                                    ?? $get('proyecto_id');

                                if (!$proyectoId && $get('barreno_id')) {
                                    $proyectoId = \App\Models\DrillHole::where('id', $get('barreno_id'))->value('proyecto_id');
                                }

                                if (!$value || !$proyectoId) {
                                    return;
                                }

                                // 1. Duplicado contra otras muestras del mismo proyecto (drafts u oficiales)
                                $existsSample = DrillHoleSample::where('proyecto_id', $proyectoId)
                                    ->where('sample_number', $value)
                                    ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                    ->with('barreno')
                                    ->first();
                                if ($existsSample) {
                                    $nombreBarreno = $existsSample->barreno?->nombre_barreno ?? 'desconocido';
                                    Notification::make()
                                        ->title('Número de muestra duplicado')
                                        ->body("La muestra '{$value}' ya existe en este proyecto (Barreno: {$nombreBarreno}). Por favor asigna un número único.")
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    $fail("El número de muestra '{$value}' ya existe en este proyecto (Barreno: {$nombreBarreno}). Debe ser único.");
                                    return;
                                }

                                // 2. Duplicado contra el registro histórico
                                $existsHist = \App\Models\HistoricalSample::where('sample_number', $value)
                                    ->where(function ($q) use ($proyectoId) {
                                        $q->where('proyecto_id', $proyectoId)->orWhereNull('proyecto_id');
                                    })
                                    ->exists();
                                if ($existsHist) {
                                    Notification::make()
                                        ->title('Número de muestra duplicado')
                                        ->body("La muestra '{$value}' ya existe en el registro histórico de muestras.")
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    $fail("El número de muestra '{$value}' ya existe en el registro histórico de muestras.");
                                }
                            },
                        ]),

                    Forms\Components\Select::make('sample_type')
                        ->label('Tipo de Muestra')
                        ->options([
                            'O'       => 'O',
                            'Control' => 'Control',
                        ])
                        ->formatStateUsing(fn($state) => strtoupper(trim((string)$state)) === 'CONTROL' ? 'Control' : 'O')
                        ->live()
                        ->required()
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            if (strtoupper(trim((string)$state)) !== 'CONTROL') {
                                $set('control_type', null);
                                $set('standard_sample_id', null);
                                $set('duplicate_sample_id', null);
                            } else {
                                $set('from_depth', null);
                                $set('to_depth', null);
                                $set('length', null);
                                $set('sample_length', null);
                            }
                        }),

                    Forms\Components\TextInput::make('from_depth')
                        ->label('Desde (FROM)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),

                    Forms\Components\TextInput::make('to_depth')
                        ->label('Hasta (TO)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL')
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            $from = (float) $get('from_depth');
                            $to = (float) $state;
                            if ($from > 0 && $to > $from) {
                                $set('length', round($to - $from, 2));
                            }
                            static::maybeAutofillLowRecoveryComment($get, $set);
                        }),

                    Forms\Components\TextInput::make('length')
                        ->label('Longitud Perforada')
                        ->numeric()
                        ->step(0.01)
                        ->readOnly()
                        ->helperText('Calculado automáticamente: TO − FROM')
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),

                    Forms\Components\TextInput::make('sample_length')
                        ->label('Recuperación')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::maybeAutofillLowRecoveryComment($get, $set);
                        })
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),

                    Forms\Components\TextInput::make('weight')
                        ->label('Peso (kg)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(fn () => (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50))
                        ->maxValue(fn () => (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00))
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),
                ]),

            // ── Sección: Tipo de Control (solo visible si es Control) ──
            Section::make('Datos de Control')
                ->columnSpanFull()
                ->columns(2)
                ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) !== 'CONTROL')
                ->schema([
                    Forms\Components\Select::make('control_type')
                        ->label('Tipo de Control')
                        ->options([
                            'Blank-ML'  => 'Blank-ML',
                            'Standard'  => 'Standard',
                            'Duplicate' => 'Duplicate',
                        ])
                        ->formatStateUsing(function ($state) {
                            $val = strtoupper(trim((string) $state));
                            if (str_contains($val, 'BLANK')) return 'Blank-ML';
                            if (str_contains($val, 'STAND')) return 'Standard';
                            if (str_contains($val, 'DUP')) return 'Duplicate';
                            return $state;
                        })
                        ->live()
                        ->searchable()
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            $val = strtoupper(trim((string) $state));
                            if (!str_contains($val, 'STAND')) {
                                $set('standard_sample_id', null);
                            }
                            if (!str_contains($val, 'DUP')) {
                                $set('duplicate_sample_id', null);
                            }
                        }),

                    Forms\Components\Select::make('standard_sample_id')
                        ->label('Estándar de Referencia')
                        ->relationship('standardSample', 'standard_name')
                        ->searchable()
                        ->preload()
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->visible(fn(Get $get) => str_contains(strtoupper(trim((string) $get('control_type'))), 'STAND')),

                    Forms\Components\Select::make('duplicate_sample_id')
                        ->label('Muestra Original (Duplicado)')
                        ->options(
                            fn(Get $get, ?DrillHoleSample $record) => DrillHoleSample::where('status', 'draft')
                                ->where('capture_source', 'import')
                                ->when($record, fn($q) => $q->where('barreno_id', $record->barreno_id))
                                ->whereNotNull('sample_number')
                                ->pluck('sample_number', 'id')
                                ->toArray()
                        )
                        ->searchable()
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->visible(fn(Get $get) => str_contains(strtoupper(trim((string) $get('control_type'))), 'DUP')),
                ]),

            // ── Sección: Work Order y Comentarios ─────────────────────
            Section::make('Operacional')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('work_order_id')
                        ->label('Orden de Trabajo')
                        ->relationship(
                            name: 'workOrder',
                            titleAttribute: 'work_order_code',
                            modifyQueryUsing: fn(Builder $query) => $query->whereDoesntHave('drillHoleSamples', fn($s) => $s->where('is_archived', true))
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->helperText(function (?DrillHoleSample $record): ?string {
                            if ($record?->isSent()) {
                                return '🔒 Esta muestra pertenece a una Work Order ya enviada al laboratorio.';
                            }
                            return null;
                        }),

                    Forms\Components\Select::make('core_size')
                        ->label('Tamaño de Núcleo')
                        ->options([
                            'PQ' => 'PQ',
                            'HQ' => 'HQ',
                            'NQ' => 'NQ',
                            'BQ' => 'BQ',
                        ])
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->nullable(),

                    Forms\Components\Textarea::make('comentarios')
                        ->label('Comentarios')
                        ->rows(2)
                        ->disabled(fn(?DrillHoleSample $record): bool => $record?->isSent() ?? false)
                        ->required(function (Get $get, ?DrillHoleSample $record) {
                            if (!$record || strtoupper(trim((string) $record->sample_type)) === 'CONTROL') return false;
                            $from = is_numeric($get('from_depth')) ? (float) $get('from_depth') : null;
                            $to = is_numeric($get('to_depth')) ? (float) $get('to_depth') : null;
                            $sl = is_numeric($get('sample_length')) ? (float) $get('sample_length') : null;
                            if ($from === null || $to === null || $to <= $from || $sl === null) return false;
                            return DrillHoleSample::isLowRecovery(round($to - $from, 2), $sl);
                        })
                        ->validationMessages([
                            'required' => 'Baja recuperación detectada (Recuperación < Longitud): los Comentarios no pueden estar vacíos.',
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Si from/to/sample_length implican "baja recuperación" y comentarios está vacío,
     * lo autocompleta con el texto estándar (sin sobrescribir un comentario existente).
     */
    public static function maybeAutofillLowRecoveryComment(Get $get, Set $set): void
    {
        $from = is_numeric($get('from_depth')) ? (float) $get('from_depth') : null;
        $to = is_numeric($get('to_depth')) ? (float) $get('to_depth') : null;
        $sl = is_numeric($get('sample_length')) ? (float) $get('sample_length') : null;

        if ($from === null || $to === null || $to <= $from || $sl === null) {
            return;
        }

        $length = round($to - $from, 2);

        if (DrillHoleSample::isLowRecovery($length, $sl)) {
            $current = (string) ($get('comentarios') ?? '');
            if (trim($current) === '') {
                $set('comentarios', DrillHoleSample::LOW_RECOVERY_COMMENT);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('sample_number', 'asc')
            ->columns([
                // ── Columna FIJA 1: ícono de estado de validación ──
                Tables\Columns\IconColumn::make('has_errors')
                    ->label('')
                    ->boolean()
                    ->state(fn($record) => $record->errors !== null && count($record->errors) > 0)
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(
                        fn($record) => $record->errors
                        ? implode("\n", $record->errors)
                        : 'Sin errores'
                    )
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:0;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:0;z-index:2;background:inherit;']),

                // ── Columna FIJA 2: BHID ──
                Tables\Columns\TextColumn::make('barreno.nombre_barreno')
                    ->label('BHID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:36px;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:36px;z-index:2;background:inherit;']),

                // ── Columna FIJA 3: No. Muestra ──
                Tables\Columns\TextColumn::make('sample_number')
                    ->label('Sample #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:140px;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:140px;z-index:2;background:inherit;']),

                // ── Columnas de intervalo ──
                Tables\Columns\TextColumn::make('from_depth')
                    ->label('From')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_depth')
                    ->label('To')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('length')
                    ->label('Drilled Length')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_length')
                    ->label('Sample Length')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Wght')
                    ->numeric(3)
                    ->sortable(),

                // ── Tipo y control ──
                Tables\Columns\TextColumn::make('sample_type')
                    ->label('Sample Type')
                    ->badge()
                    ->color(fn($state) => strtoupper((string) $state) === 'CONTROL' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('control_type')
                    ->label('Control Type')
                    ->searchable()
                    ->placeholder('—')
                    ->badge()
                    ->color(fn($state) => ($state && str_starts_with($state, 'STD-')) ? 'info' : 'gray')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === 'Standard' && $record->standardSample) {
                            return 'STD-' . $record->standardSample->standard_name;
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'draft' => 'warning',
                        'official' => 'success',
                        default => 'gray',
                    }),

                // ── Columna de errores / gaps (texto resumido) ──
                /* Tables\Columns\TextColumn::make('errors_summary')
                     ->label('Problemas')
                     ->state(fn($record) => $record->errors
                         ? implode(' │ ', array_map(fn($e) => mb_substr($e, 0, 60), $record->errors))
                         : null
                     )
                     ->placeholder('—')
                     ->color('danger')
                     ->wrap()
                     ->limit(80),*/

                Tables\Columns\IconColumn::make('comentarios')
                    ->label('Comentarios')
                    ->state(fn($record) => !empty($record->comentarios))
                    ->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-left-right')
                    ->falseIcon(null)
                    ->color('gray')
                    ->tooltip(fn($record) => $record->comentarios ?: null),

                Tables\Columns\TextColumn::make('sampled_at')
                    ->label('Fecha Muestreo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('con_errores')
                    ->label('Con Errores')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('errors')),

                Tables\Filters\Filter::make('sin_errores')
                    ->label('Sin Errores')
                    ->query(fn(Builder $query): Builder => $query->whereNull('errors')),

                Tables\Filters\Filter::make('solo_controles')
                    ->label('Solo Controles')
                    ->query(fn(Builder $query): Builder => $query->whereRaw("UPPER(TRIM(sample_type)) = 'CONTROL'")),
            ])
            ->actions([
                // EditAction abre como slide-over lateral
                \Filament\Actions\EditAction::make()
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->disabled(fn(DrillHoleSample $record): bool => $record->workOrder?->isSent() ?? false)
                    ->tooltip(fn(DrillHoleSample $record): ?string => ($record->workOrder?->isSent() ?? false)
                        ? 'Esta muestra está bloqueada: pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->before(function (\Filament\Actions\EditAction $action, DrillHoleSample $record, array $data) {
                        $nuevoNumero = trim($data['sample_number'] ?? '');
                        $proyectoId  = $record->proyecto_id ?? $record->barreno?->proyecto_id;

                        if (!empty($nuevoNumero) && $proyectoId) {
                            $duplicada = DrillHoleSample::where('proyecto_id', $proyectoId)
                                ->where('sample_number', $nuevoNumero)
                                ->where('id', '!=', $record->id)
                                ->with('barreno')
                                ->first();

                            if ($duplicada) {
                                $nombreBarreno = $duplicada->barreno?->nombre_barreno ?? 'desconocido';
                                Notification::make()
                                    ->title('Número de muestra duplicado')
                                    ->body("La muestra '{$nuevoNumero}' ya existe en este proyecto (Barreno: {$nombreBarreno}). Por favor asigna un número único.")
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                $action->halt();
                            }
                        }
                    })
                    ->after(function (DrillHoleSample $record, \App\Services\SampleValidationService $service) {
                        $service->validateDraftsForUser($record->user_id);
                    }),
                DeleteAction::make()
                    ->disabled(fn(DrillHoleSample $record): bool => $record->workOrder?->isSent() ?? false)
                    ->tooltip(fn(DrillHoleSample $record): ?string => ($record->workOrder?->isSent() ?? false)
                        ? 'No se puede eliminar: esta muestra pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->after(function (DrillHoleSample $record, \App\Services\SampleValidationService $service) {
                        $service->validateDraftsForUser($record->user_id);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('exportExcel')
                        ->label('Exportar a Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Support\Collection $records, SampleExportService $service) {
                            return $service->exportToExcel($records);
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('assignWorkOrder')
                        ->label('Asignar Work Order')
                        ->icon('heroicon-o-briefcase')
                        ->color('info')
                        ->form(function (\Illuminate\Support\Collection $records) {
                            $sedeId = null;
                            $barrenoId = null;
                            $firstRecord = $records->first();
                            if ($firstRecord) {
                                $sedeId = $firstRecord->proyecto?->sede_id ?? $firstRecord->barreno?->sede_id;
                                $barrenoId = $firstRecord->barreno_id;
                            }

                            return [
                                Forms\Components\Select::make('work_order_id')
                                    ->label('Work Order')
                                    ->options(function () use ($sedeId, $barrenoId) {
                                        $query = WorkOrder::query()
                                            ->select(['id', 'work_order_code', 'samples_quantity']);
                                        if ($sedeId) {
                                            $query->where('sede_id', $sedeId);
                                        }
                                        $query->where(function ($q) {
                                            $q->where('sent_to_lab', false)->orWhereNull('sent_to_lab');
                                        });
                                        // Only show WOs that have NOT been sent to the lab
                                        $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('is_archived', true));
                                        if ($barrenoId) {
                                            $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('barreno_id', '!=', $barrenoId));
                                        }
                                        return $query->orderByDesc('id')
                                            ->get()
                                            ->mapWithKeys(
                                                fn($wo) =>
                                                [$wo->id => "{$wo->work_order_code} (muestras: {$wo->samples_quantity})"]
                                            );
                                    })
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('work_order_code')
                                            ->label('Código de nueva Work Order (6 dígitos)')
                                            ->required()
                                            ->numeric()
                                            ->length(6)
                                            ->rules(['digits:6'])
                                            ->unique('work_orders', 'work_order_code')
                                            ->placeholder('Ej: 260801'),
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
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            $targetIds = $records->pluck('id')->all();
                            if (empty($targetIds)) {
                                return;
                            }

                            // Verificar en 1 consulta que ninguna de las muestras seleccionadas pertenezca a una WO ya enviada
                            $hasLockedSamples = DrillHoleSample::whereIn('id', $targetIds)
                                ->where(function ($q) {
                                    $q->where('is_archived', true)
                                      ->orWhereHas('workOrder', fn($wq) => $wq->where('sent_to_lab', true));
                                })
                                ->exists();

                            if ($hasLockedSamples) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Operación no permitida')
                                    ->body('Una o más muestras seleccionadas ya pertenecen a una Work Order enviada al laboratorio y no pueden reasignarse.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $barrenoIds = $records->pluck('barreno_id')->unique();
                            if ($barrenoIds->count() > 1) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Operación no permitida')
                                    ->body('Las muestras seleccionadas deben pertenecer al mismo barreno para asignarles una Orden de Trabajo.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $barrenoId = $barrenoIds->first();
                            $workOrder = WorkOrder::find($data['work_order_id']);

                            if (!$workOrder) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Work Order no encontrada')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Verificar que la WO destino no esté enviada
                            if ($workOrder->isSent()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Work Order bloqueada')
                                    ->body("La Work Order {$workOrder->work_order_code} ya fue enviada al laboratorio y no puede recibir más muestras.")
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
                                        ->body('Esta Work Order ya tiene muestras de otros barrenos.')
                                        ->warning()
                                        ->send();
                                    return;
                                }
                            }

                            // Validar en 1 consulta que todas las muestras seleccionadas tengan Core Size capturado
                            $barrenoCoreSize = $barrenoId
                                ? \App\Models\DrillHole::where('id', $barrenoId)->value('core_size')
                                : null;

                            if (empty($barrenoCoreSize)) {
                                $missingCoreSizeQuery = DrillHoleSample::whereIn('id', $targetIds)
                                    ->where(function ($q) {
                                        $q->whereNull('core_size')->orWhere('core_size', '');
                                    });

                                $missingCount = (clone $missingCoreSizeQuery)->count();
                                if ($missingCount > 0) {
                                    $sampleNumbers = (clone $missingCoreSizeQuery)
                                        ->orderBy('sample_number', 'asc')
                                        ->limit(5)
                                        ->pluck('sample_number')
                                        ->implode(', ');
                                    \Filament\Notifications\Notification::make()
                                        ->title('Core Size requerido')
                                        ->body("No se puede asignar la Work Order porque {$missingCount} muestra(s) no tienen Core Size asignado ({$sampleNumbers}...). Por favor asigna el Core Size antes de continuar.")
                                        ->danger()
                                        ->send();
                                    return;
                                }
                            }

                            \Illuminate\Support\Facades\DB::transaction(function () use ($targetIds, $workOrder) {
                                $previousWoIds = DrillHoleSample::whereIn('id', $targetIds)
                                    ->whereNotNull('work_order_id')
                                    ->distinct()
                                    ->pluck('work_order_id');

                                DrillHoleSample::whereIn('id', $targetIds)
                                    ->update(['work_order_id' => $workOrder->id]);

                                $affectedWoIds = $previousWoIds->push($workOrder->id)->unique()->filter()->values();
                                foreach ($affectedWoIds as $woIdToUpdate) {
                                    $count = DrillHoleSample::where('work_order_id', $woIdToUpdate)->count();
                                    WorkOrder::where('id', $woIdToUpdate)->update(['samples_quantity' => $count]);
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Work Order asignada')
                                ->body("La orden {$workOrder->work_order_code} fue asignada a " . count($targetIds) . " muestras.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->before(function (\Illuminate\Support\Collection $records, \Filament\Actions\DeleteBulkAction $action) {
                            $locked = $records->filter(fn(DrillHoleSample $s) => $s->isSent());
                            if ($locked->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Operación no permitida')
                                    ->body('No se pueden eliminar muestras que pertenecen a una Work Order ya enviada al laboratorio.')
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        })
                        ->after(function (\App\Services\SampleValidationService $service) {
                            $service->validateDraftsForUser(auth()->id());
                        }),
                ]),
            ])
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->with(['standardSample', 'barreno'])
                    ->where('status', 'draft')
                    ->where('capture_source', 'import')
                    ->where('user_id', auth()->id())
            );
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDraftDrillHoles::route('/'),
            'review' => Pages\ReviewDrillHoleSamples::route('/{record}/review'),
        ];
    }
}
