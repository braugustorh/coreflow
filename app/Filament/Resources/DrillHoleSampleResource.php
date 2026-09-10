<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrillHoleSampleResource\Pages;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Models\WorkOrder;
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
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sample_number')
                        ->label('No. Muestra')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\Select::make('sample_type')
                        ->label('Tipo de Muestra')
                        ->options([
                            'O'       => 'O',
                            'Control' => 'Control',
                        ])
                        ->formatStateUsing(fn($state) => strtoupper(trim((string)$state)) === 'CONTROL' ? 'Control' : 'O')
                        ->live()
                        ->required()
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
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),

                    Forms\Components\TextInput::make('to_depth')
                        ->label('Hasta (TO)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL')
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            $from = (float) $get('from_depth');
                            $to = (float) $state;
                            if ($from > 0 && $to > $from) {
                                $set('length', round($to - $from, 2));
                            }
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
                        ->hidden(fn(Get $get) => strtoupper(trim((string) $get('sample_type'))) === 'CONTROL'),

                    Forms\Components\TextInput::make('weight')
                        ->label('Peso (kg)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(fn () => (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50))
                        ->maxValue(fn () => (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00))
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
                        ->visible(fn(Get $get) => str_contains(strtoupper(trim((string) $get('control_type'))), 'DUP')),
                ]),

            // ── Sección: Work Order y Comentarios ─────────────────────
            Section::make('Operacional')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('work_order_id')
                        ->label('Orden de Trabajo')
                        ->relationship('workOrder', 'work_order_code')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('core_size')
                        ->label('Tamaño de Núcleo')
                        ->options([
                            'PQ' => 'PQ',
                            'HQ' => 'HQ',
                            'NQ' => 'NQ',
                            'BQ' => 'BQ',
                        ])
                        ->nullable(),

                    Forms\Components\Textarea::make('comentarios')
                        ->label('Comentarios')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ];
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
                    ->after(function (DrillHoleSample $record, \App\Services\SampleValidationService $service) {
                        $service->validateDraftsForUser($record->user_id);
                    }),
                DeleteAction::make()
                    ->after(function (DrillHoleSample $record, \App\Services\SampleValidationService $service) {
                        $service->validateDraftsForUser($record->user_id);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('assignWorkOrder')
                        ->label('Asignar Work Order')
                        ->icon('heroicon-o-briefcase')
                        ->color('info')
                        ->form(function (\Illuminate\Support\Collection $records) {
                            $sedeId = null;
                            $firstRecord = $records->first();
                            if ($firstRecord) {
                                $sedeId = $firstRecord->proyecto?->sede_id;
                            }

                            return [
                                Forms\Components\Select::make('work_order_id')
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
                                        Forms\Components\TextInput::make('work_order_code')
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
                        ->action(function (\Illuminate\Support\Collection $records, array $data, \App\Services\SampleValidationService $service) {
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
                                if ($prevWoId === $workOrder->id) {
                                    continue;
                                }
                                $wo = WorkOrder::find($prevWoId);
                                if ($wo) {
                                    $wo->samples_quantity = DrillHoleSample::where('work_order_id', $prevWoId)->count();
                                    $wo->save();
                                }
                            }

                            // Recalcular conteo de la WO nueva/seleccionada
                            $workOrder->samples_quantity = DrillHoleSample::where('work_order_id', $workOrder->id)->count();
                            $workOrder->save();

                            // Re-validar todo automáticamente para limpiar errores de Work Order
                            $service->validateDraftsForUser(auth()->id());

                            \Filament\Notifications\Notification::make()
                                ->title('Work Order asignada')
                                ->body("La orden {$workOrder->work_order_code} fue asignada a {$records->count()} muestras.")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make()
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
