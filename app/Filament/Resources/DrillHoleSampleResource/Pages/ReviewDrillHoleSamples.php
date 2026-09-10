<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Pages;

use App\Filament\Resources\DrillHoleSampleResource;
use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Models\WorkOrder;
use App\Services\SampleValidationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Enums\Width;
use App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReviewDrillHoleSamples extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = DrillHoleSampleResource::class;

    protected string $view = 'filament.resources.drill-hole-sample-resource.pages.review-drill-hole-samples';

    public int|string $record;

    public ?DrillHole $drillHole = null;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(int|string $record): void
    {
        $this->record = $record;
        $this->drillHole = DrillHole::with(['sede', 'proyecto'])->findOrFail($record);
    }

    public function getTitle(): string
    {
        return "Revisión de Muestras: {$this->drillHole?->nombre_barreno}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            DrillHoleSampleResource::getUrl('index') => 'Bandeja de Barrenos en Borrador',
            '#' => $this->drillHole?->nombre_barreno ?? 'Revisión',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assign_core_size_header')
                ->label('Asignar Core Size')
                ->icon('heroicon-o-circle-stack')
                ->color('info')
                ->form([
                    Forms\Components\Radio::make('scope')
                        ->label('Aplicar a')
                        ->options([
                            'all'    => 'Todas las muestras de este barreno',
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
                            'PQ' => 'PQ',
                            'HQ' => 'HQ',
                            'NQ' => 'NQ',
                            'BQ' => 'BQ',
                        ])
                        ->required()
                        ->native(false),
                ])
                ->modalHeading("Asignar Core Size a {$this->drillHole?->nombre_barreno}")
                ->modalDescription('Selecciona las muestras y el Core Size a aplicar.')
                ->action(function (array $data, SampleValidationService $service) {
                    $barrenoId = (int)$this->record;
                    $baseQuery = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import');

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

                    // Re-validar este barreno
                    $service->validateDraftsForBarreno($barrenoId);

                    Notification::make()
                        ->title("Core Size {$coreSize} asignado a {$updatedCount} muestra(s).")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('validate_data')
                ->label('Validar Datos')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->action(function (SampleValidationService $service) {
                    $barrenoId = (int)$this->record;
                    $service->validateDraftsForBarreno($barrenoId);

                    $errorsCount = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->whereNotNull('errors')
                        ->count();

                    if ($errorsCount > 0) {
                        Notification::make()
                            ->title("{$errorsCount} registros con observaciones detectadas")
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

            Actions\Action::make('officialize_data')
                ->label('Acreditar / Oficializar Barreno')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading("Acreditar y Oficializar {$this->drillHole?->nombre_barreno}")
                ->modalDescription('¿Confirmas que deseas acreditar oficialmente todas las muestras de este barreno? Pasarán a estatus oficial y se cerrará el borrador.')
                ->action(function (SampleValidationService $service) {
                    $barrenoId = (int)$this->record;

                    // Re-validar antes de oficializar
                    $service->validateDraftsForBarreno($barrenoId);

                    $baseQuery = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import');

                    $totalDrafts = (clone $baseQuery)->count();
                    if ($totalDrafts === 0) {
                        Notification::make()
                            ->title('No hay registros para oficializar en este barreno.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $draftsWithErrors = (clone $baseQuery)->whereNotNull('errors')->count();
                    $draftsWithoutWorkOrder = (clone $baseQuery)->whereNull('work_order_id')->count();
                    $draftsWithoutCoreSize = (clone $baseQuery)->whereNull('core_size')->count();

                    if ($draftsWithErrors > 0 || $draftsWithoutWorkOrder > 0 || $draftsWithoutCoreSize > 0) {
                        $messages = [];
                        if ($draftsWithErrors > 0) {
                            $messages[] = "• {$draftsWithErrors} registros con errores pendientes por corregir.";
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
                        return;
                    }

                    DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->update(['status' => 'official']);

                    Notification::make()
                        ->title("El barreno {$this->drillHole?->nombre_barreno} ha sido acreditado oficialmente.")
                        ->success()
                        ->send();

                    $this->redirect(DrillHoleSampleResource::getUrl('index'));
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $barrenoId = (int)$this->record;
        $barrenoSedeId = $this->drillHole?->sede_id ?? $this->drillHole?->proyecto?->sede_id;

        return $table
            ->query(
                DrillHoleSample::query()
                    ->where('barreno_id', $barrenoId)
                    ->where('status', 'draft')
                    ->where('capture_source', 'import')
                    ->with(['standardSample', 'duplicateSample', 'barreno', 'workOrder'])
            )
            ->deferLoading()
            ->defaultSort('sample_number', 'asc')
            ->columns([
                // Columna 1: Estado de validación
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

                // Columna 2: BHID
                Tables\Columns\TextColumn::make('barreno.nombre_barreno')
                    ->label('BHID')
                    ->weight('bold')
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:36px;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:36px;z-index:2;background:inherit;']),

                // Columna 3: No. Muestra
                Tables\Columns\TextColumn::make('sample_number')
                    ->label('Sample #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:140px;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:140px;z-index:2;background:inherit;']),

                // Intervalos
                Tables\Columns\TextColumn::make('from_depth')
                    ->label('From')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_depth')
                    ->label('To')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('length')
                    ->label('Longitud')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_length')
                    ->label('Recup.')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('core_size')
                    ->label('Core Size')
                    ->badge()
                    ->color(fn(?string $state) => empty($state) ? 'danger' : 'info')
                    ->formatStateUsing(fn(?string $state) => empty($state) ? 'Sin asignar' : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_type')
                    ->label('SAMPLE TYPE')
                    ->badge()
                    ->state(fn($record) => strtoupper(trim((string)$record->sample_type)) === 'CONTROL' ? 'Control' : 'O')
                    ->color(fn(string $state): string => match ($state) {
                        'O'       => 'gray',
                        'Control' => 'info',
                        default   => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('control_type')
                    ->label('Control')
                    ->formatStateUsing(function ($record) {
                        $sampleType = strtoupper(trim((string) $record->sample_type));
                        if ($sampleType !== 'CONTROL') {
                            return '—';
                        }

                        $controlType = strtoupper(trim((string) $record->control_type));

                        // 1. Blanco -> Blank-ML
                        if (str_contains($controlType, 'BLANK')) {
                            return 'Blank-ML';
                        }

                        // 2. Standard -> el estándar que es (verificado con StandardSample en BD)
                        if (str_contains($controlType, 'STAND') || $record->standard_sample_id) {
                            $standardName = $record->standardSample?->standard_name;
                            if (!$standardName && !empty($record->control_type) && !in_array($controlType, ['STANDARD', 'STAND'])) {
                                $standardName = \App\Models\StandardSample::where('standard_name', $record->control_type)->value('standard_name');
                            }
                            return $standardName ?? $record->control_type ?? 'Standard';
                        }

                        // 3. Duplicado -> DUP-(y el numero de muestra a la que está haciendo referencia)
                        if (str_contains($controlType, 'DUP') || $record->duplicate_sample_id) {
                            $dupNumber = $record->duplicateSample?->sample_number;
                            if (!$dupNumber && $record->duplicate_sample_id) {
                                $dupNumber = \App\Models\DrillHoleSample::where('id', $record->duplicate_sample_id)->value('sample_number');
                            }
                            return $dupNumber ? "DUP-{$dupNumber}" : 'DUP';
                        }

                        // Si por algún motivo tiene un valor libre que coincide con un estándar en BD
                        $matchedStandard = \App\Models\StandardSample::where('standard_name', $record->control_type)->value('standard_name');
                        if ($matchedStandard) {
                            return $matchedStandard;
                        }

                        return $record->control_type ?: 'Blank-ML';
                    }),

                Tables\Columns\TextColumn::make('workOrder.work_order_code')
                    ->label('Work Order')
                    ->badge()
                    ->color(fn(?string $state) => empty($state) ? 'danger' : 'success')
                    ->formatStateUsing(fn(?string $state) => empty($state) ? 'Sin asignar' : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Peso (kg)')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('comentarios')
                    ->label('Comentarios')
                    ->limit(25),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->form(fn() => DrillHoleSampleResource::getSampleFormSchema())
                    ->after(function (DrillHoleSample $record, SampleValidationService $service) use ($barrenoId) {
                        $service->validateDraftsForBarreno($barrenoId);
                    }),

                DeleteAction::make()
                    ->after(function (SampleValidationService $service) use ($barrenoId) {
                        $service->validateDraftsForBarreno($barrenoId);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_registro')
                    ->label('Filtrar registros')
                    ->placeholder('Todos los registros')
                    ->options([
                        'gaps'       => 'Gaps',
                        'errors'     => 'Registro con errores',
                        'originals'  => 'Registros de Originales',
                        'standards'  => 'Estandards',
                        'blanks'     => 'Blancos',
                        'duplicates' => 'Duplicados',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);
                        $values = array_filter($values);

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->where(function (Builder $outer) use ($values) {
                            foreach ($values as $val) {
                                $outer->orWhere(function (Builder $q) use ($val) {
                                    match ($val) {
                                        'gaps' => $q->where(function (Builder $sub) {
                                            $sub->whereNotNull('errors')->where('errors', 'like', '%GAP%')
                                                ->orWhere('comentarios', 'like', '%GAP%');
                                        }),
                                        'errors' => $q->whereNotNull('errors')
                                            ->where('errors', '!=', '[]')
                                            ->where('errors', '!=', 'null'),
                                        'originals' => $q->where(function (Builder $sub) {
                                            $sub->whereRaw("UPPER(TRIM(sample_type)) = 'O'")
                                                ->orWhere(fn($s) => $s->whereNull('sample_type')->whereNull('control_type'));
                                        }),
                                        'standards' => $q->where(function (Builder $sub) {
                                            $sub->whereNotNull('standard_sample_id')
                                                ->orWhereRaw("UPPER(TRIM(control_type)) = 'STANDARD'")
                                                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%STAND%'")
                                                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE 'STD-%'");
                                        }),
                                        'blanks' => $q->where(function (Builder $sub) {
                                            $sub->whereRaw("UPPER(TRIM(control_type)) LIKE '%BLANK%'")
                                                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%BLK%'")
                                                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%BLANCO%'");
                                        }),
                                        'duplicates' => $q->where(function (Builder $sub) {
                                            $sub->whereNotNull('duplicate_sample_id')
                                                ->orWhereRaw("UPPER(TRIM(control_type)) = 'DUPLICATE'")
                                                ->orWhereRaw("UPPER(TRIM(control_type)) LIKE '%DUP%'");
                                        }),
                                        default => null,
                                    };
                                });
                            }
                        });
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_work_order')
                        ->label('Asignar Work Order')
                        ->icon('heroicon-o-briefcase')
                        ->color('info')
                        ->form(function () use ($barrenoSedeId) {
                            return [
                                Forms\Components\Select::make('work_order_id')
                                    ->label('Work Order')
                                    ->options(function () use ($barrenoSedeId) {
                                        $query = WorkOrder::query();
                                        if ($barrenoSedeId) {
                                            $query->where('sede_id', $barrenoSedeId);
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
                                            ->label('Código de nueva Work Order (6 dígitos)')
                                            ->required()
                                            ->numeric()
                                            ->length(6)
                                            ->rules(['digits:6'])
                                            ->unique('work_orders', 'work_order_code')
                                            ->placeholder('Ej: 260801'),
                                    ])
                                    ->createOptionUsing(function (array $data) use ($barrenoSedeId) {
                                        $wo = WorkOrder::create([
                                            'work_order_code' => trim($data['work_order_code']),
                                            'sede_id' => $barrenoSedeId,
                                            'samples_quantity' => 0,
                                        ]);
                                        return $wo->id;
                                    }),
                            ];
                        })
                        ->action(function (Collection $records, array $data, SampleValidationService $service) use ($barrenoId) {
                            $workOrder = WorkOrder::find($data['work_order_id']);

                            if (!$workOrder) {
                                Notification::make()->title('Work Order no encontrada')->danger()->send();
                                return;
                            }

                            // Si la Work Order tiene muestras, verificar que sean del mismo barreno
                            if ($workOrder->samples_quantity > 0) {
                                $allSameBarreno = DrillHoleSample::where('work_order_id', $workOrder->id)
                                    ->where('barreno_id', '!=', $barrenoId)
                                    ->doesntExist();

                                if (!$allSameBarreno) {
                                    Notification::make()
                                        ->title('Work Order no disponible')
                                        ->body('Esta Work Order ya tiene muestras de otros barrenos.')
                                        ->warning()
                                        ->send();
                                    return;
                                }
                            }

                            // Validar que todas las muestras seleccionadas tengan Core Size
                            $samplesWithoutCoreSize = $records->filter(function ($sample) {
                                return empty($sample->core_size);
                            });

                            if ($samplesWithoutCoreSize->isNotEmpty()) {
                                $count = $samplesWithoutCoreSize->count();
                                $sampleNumbers = $samplesWithoutCoreSize->pluck('sample_number')->take(5)->implode(', ');
                                Notification::make()
                                    ->title('Core Size requerido')
                                    ->body("No se puede asignar la Work Order porque {$count} muestra(s) no tienen Core Size asignado ({$sampleNumbers}...). Por favor asigna el Core Size antes de continuar.")
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $previousWoIds = $records->pluck('work_order_id')->filter()->unique()->values();

                            foreach ($records as $record) {
                                $record->update(['work_order_id' => $workOrder->id]);
                            }

                            // Recalcular conteos
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

                            $workOrder->samples_quantity = DrillHoleSample::where('work_order_id', $workOrder->id)->count();
                            $workOrder->save();

                            $service->validateDraftsForBarreno($barrenoId);

                            Notification::make()
                                ->title('Work Order asignada')
                                ->body("La orden {$workOrder->work_order_code} fue asignada a {$records->count()} muestras.")
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make()
                        ->after(function (SampleValidationService $service) use ($barrenoId) {
                            $service->validateDraftsForBarreno($barrenoId);
                        }),
                ]),
            ]);
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
