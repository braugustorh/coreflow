<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Pages;

use App\Filament\Resources\DrillHoleSampleResource;
use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\StandardSample;
use App\Models\WorkOrder;
use App\Services\SampleExportService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use App\Filament\Actions\QcPhotosAction;

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

        $this->notifyLowRecoveryPendingIfAny();
    }

    /**
     * Notificación emergente (toast) al entrar a la revisión si existen muestras
     * con baja recuperación (Sample Length < Length) sin comentario asignado.
     * Complementa el banner fijo y la acción de cabecera para evitar que pase
     * desapercibido en barrenos con cientos de muestras.
     */
    protected function notifyLowRecoveryPendingIfAny(): void
    {
        $pending = $this->getLowRecoveryPendingCountProperty();

        if ($pending > 0) {
            Notification::make()
                ->title("⚠️ {$pending} muestra(s) con baja recuperación sin comentario")
                ->body('Se detectaron muestras con Sample Length < Length cuyo campo Comentarios está vacío. Usa el botón "Baja Recuperación" en la parte superior para revisarlas o aplicar el comentario masivamente.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * Conteo de muestras del barreno con baja recuperación y comentarios vacíos.
     * Usado por el banner fijo, la notificación de entrada y la acción de cabecera.
     */
    public function getLowRecoveryPendingCountProperty(): int
    {
        return DrillHoleSample::where('barreno_id', (int) $this->record)
            ->where('status', 'draft')
            ->lowRecoveryMissingComment()
            ->count();
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
            Actions\Action::make('qc_photos_evidence')
                ->label(function () {
                    $pending = DrillHoleSample::where('barreno_id', (int)$this->record)
                        ->where('status', 'draft')
                        ->requiringQcPhotos()
                        ->missingQcPhotos()
                        ->count();
                    return $pending > 0 ? "Fotos QC ({$pending} pendientes)" : 'Fotos QC';
                })
                ->icon('heroicon-o-camera')
                ->color(function () {
                    $pending = DrillHoleSample::where('barreno_id', (int)$this->record)
                        ->where('status', 'draft')
                        ->requiringQcPhotos()
                        ->missingQcPhotos()
                        ->count();
                    return $pending > 0 ? 'warning' : 'gray';
                })
                ->url(fn () => \App\Filament\Pages\QcPhotoEvidence::getUrl(['barrenoId' => (int)$this->record, 'filter' => 'pending'])),

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

                    // Re-validar este barreno
                    $service->validateDraftsForBarreno($barrenoId);

                    Notification::make()
                        ->title("Core Size {$coreSize} asignado a {$updatedCount} muestra(s).")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('assign_work_order_header')
                ->label('Asignar Work Order')
                ->icon('heroicon-o-briefcase')
                ->color('info')
                ->fillForm(function (): array {
                    $barrenoId = (int) $this->record;
                    $firstPending = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->where('is_archived', false)
                        ->whereNull('work_order_id')
                        ->orderBy('sample_number', 'asc')
                        ->value('sample_number');

                    return [
                        'scope'        => 'next_batch',
                        'batch_size'   => 75,
                        'sample_start' => $firstPending ?? '',
                        'sample_end'   => '',
                    ];
                })
                ->form(function (): array {
                    $barrenoId = (int) $this->record;
                    $barrenoSedeId = $this->drillHole?->sede_id ?? $this->drillHole?->proyecto?->sede_id;

                    return [
                        Forms\Components\Radio::make('scope')
                            ->label('Aplicar a')
                            ->options([
                                'next_batch'  => 'Siguiente bloque de N muestras pendientes (sin WO)',
                                'range'       => 'Rango de muestras (Muestra Inicio X a Muestra Fin Y)',
                                'all_pending' => 'Todas las muestras pendientes (sin WO) de este barreno',
                                'all'         => 'Todas las muestras de este barreno (incluyendo reasignación)',
                                'single'      => 'Muestra única',
                            ])
                            ->default('next_batch')
                            ->live(),

                        Forms\Components\TextInput::make('batch_size')
                            ->label('Cantidad de muestras del bloque (N)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2000)
                            ->default(75)
                            ->required(fn(Get $get) => $get('scope') === 'next_batch')
                            ->hidden(fn(Get $get) => $get('scope') !== 'next_batch')
                            ->live(debounce: 300),

                        Forms\Components\Placeholder::make('batch_preview')
                            ->label('Vista previa de asignación')
                            ->hidden(fn(Get $get) => !in_array($get('scope'), ['next_batch', 'all_pending']))
                            ->content(function (Get $get) use ($barrenoId): HtmlString {
                                $pendingSamples = DrillHoleSample::where('barreno_id', $barrenoId)
                                    ->where('status', 'draft')
                                    ->where('capture_source', 'import')
                                    ->where('is_archived', false)
                                    ->whereNull('work_order_id')
                                    ->orderBy('sample_number', 'asc')
                                    ->get(['id', 'sample_number']);

                                $totalPending = $pendingSamples->count();
                                if ($totalPending === 0) {
                                    return new HtmlString('<span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">✓ Todas las muestras de este barreno ya tienen Work Order asignada.</span>');
                                }

                                if ($get('scope') === 'all_pending') {
                                    $first = e($pendingSamples->first()->sample_number);
                                    $last  = e($pendingSamples->last()->sample_number);
                                    return new HtmlString("<span class=\"text-xs text-gray-600 dark:text-gray-300\">Se asignarán las <strong>{$totalPending}</strong> muestras pendientes: desde <strong>{$first}</strong> hasta <strong>{$last}</strong>.</span>");
                                }

                                $n = max(1, (int) ($get('batch_size') ?: 75));
                                $slice = $pendingSamples->take($n);
                                $count = $slice->count();
                                $first = e($slice->first()->sample_number);
                                $last  = e($slice->last()->sample_number);

                                return new HtmlString("<span class=\"text-xs text-gray-600 dark:text-gray-300\">Pendientes sin WO: <strong>{$totalPending}</strong>. Este bloque tomará <strong>{$count}</strong> muestra(s): desde <strong>{$first}</strong> hasta <strong>{$last}</strong>.</span>");
                            }),

                        Forms\Components\TextInput::make('sample_start')
                            ->label('Muestra Inicio (X)')
                            ->placeholder('Ej: ML157100')
                            ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                            ->hidden(fn(Get $get) => !in_array($get('scope'), ['range', 'single'])),

                        Forms\Components\TextInput::make('sample_end')
                            ->label('Muestra Fin (Y)')
                            ->placeholder('Ej: ML157175')
                            ->required(fn(Get $get) => $get('scope') === 'range')
                            ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                        $this->getWorkOrderSelectField($barrenoSedeId, $barrenoId),
                    ];
                })
                ->modalHeading("Asignar Work Order a {$this->drillHole?->nombre_barreno}")
                ->modalDescription('Selecciona el bloque o rango de muestras y la Orden de Trabajo a asignar.')
                ->action(function (array $data) {
                    $barrenoId = (int) $this->record;
                    $baseQuery = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->where('capture_source', 'import')
                        ->where('is_archived', false)
                        ->whereDoesntHave('workOrder', fn($q) => $q->where('sent_to_lab', true)->orWhereHas('drillHoleSamples', fn($s) => $s->where('is_archived', true)))
                        ->orderBy('sample_number', 'asc');

                    $scope = $data['scope'] ?? 'next_batch';
                    $targetIds = [];

                    if ($scope === 'all') {
                        $targetIds = (clone $baseQuery)->pluck('id')->all();
                    } elseif ($scope === 'all_pending') {
                        $targetIds = (clone $baseQuery)->whereNull('work_order_id')->pluck('id')->all();
                    } elseif ($scope === 'next_batch') {
                        $n = max(1, (int) ($data['batch_size'] ?? 75));
                        $targetIds = (clone $baseQuery)
                            ->whereNull('work_order_id')
                            ->limit($n)
                            ->pluck('id')
                            ->all();
                    } elseif ($scope === 'single') {
                        $sampleStart = trim((string) ($data['sample_start'] ?? ''));
                        $targetIds = (clone $baseQuery)
                            ->where('sample_number', $sampleStart)
                            ->pluck('id')
                            ->all();
                    } elseif ($scope === 'range') {
                        $sampleStart = trim((string) ($data['sample_start'] ?? ''));
                        $sampleEnd   = trim((string) ($data['sample_end'] ?? ''));
                        $draftSamples = (clone $baseQuery)->get(['id', 'sample_number']);
                        $targetIds = $this->getSampleIdsInRange($draftSamples, $sampleStart, $sampleEnd);
                    }

                    $this->executeWorkOrderAssignment($targetIds, (int) $data['work_order_id']);
                }),
            /*
            Actions\Action::make('apply_low_recovery_comment_header')
                ->label(function () {
                    $pending = $this->getLowRecoveryPendingCountProperty();
                    return $pending > 0 ? "⚠️ Baja Recuperación ({$pending} pendientes)" : 'Baja Recuperación';
                })
                ->icon('heroicon-o-exclamation-triangle')
                ->color(fn () => $this->getLowRecoveryPendingCountProperty() > 0 ? 'warning' : 'gray')
                ->visible(fn () => $this->getLowRecoveryPendingCountProperty() > 0)
                ->requiresConfirmation()
                ->modalHeading('Aplicar comentario de Baja Recuperación')
                ->modalDescription('Se detectaron muestras con Sample Length < Length cuyo campo Comentarios está vacío. ¿Deseas aplicar automáticamente el comentario "baja recuperacion" a todos estos registros del barreno?')
                ->modalSubmitActionLabel('Sí, aplicar a todos')
                ->action(function (SampleValidationService $service) {
                    $barrenoId = (int) $this->record;
                    $updatedCount = DrillHoleSample::where('barreno_id', $barrenoId)
                        ->where('status', 'draft')
                        ->lowRecoveryMissingComment()
                        ->update(['comentarios' => DrillHoleSample::LOW_RECOVERY_COMMENT]);

                    if ($updatedCount > 0) {
                        $service->validateDraftsForBarreno($barrenoId);
                    }

                    Notification::make()
                        ->title($updatedCount > 0
                            ? "Comentario 'baja recuperacion' aplicado a {$updatedCount} muestra(s)."
                            : 'No se encontraron muestras pendientes de este comentario.')
                        ->success()
                        ->send();
                }),
            */
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
            ->heading("Barreno: " . ($this->drillHole?->nombre_barreno ?? 'N/A'))
            ->description("Distrito: " . ($this->drillHole?->sede?->name ?? 'N/A') . " | Proyecto: " . ($this->drillHole?->proyecto?->nombre ?? 'N/A'))
            ->query(
                DrillHoleSample::query()
                    ->where('barreno_id', $barrenoId)
                    ->where('status', 'draft')
                    ->where('capture_source', 'import')
                    ->with(['standardSample', 'duplicateSample', 'barreno', 'workOrder', 'qcPhotos'])
                    ->withCount('qcPhotos')
            )
            ->deferLoading()
            ->defaultSort('sample_number', 'asc')
            ->defaultPaginationPageOption(75)
            ->paginationPageOptions([50, 75, 100, 200])
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

                // Columna 2: No. Muestra
                Tables\Columns\TextColumn::make('sample_number')
                    ->label('Sample')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->extraHeaderAttributes(['style' => 'position:sticky;left:36px;z-index:3;background:inherit;'])
                    ->extraAttributes(['style' => 'position:sticky;left:36px;z-index:2;background:inherit;']),

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
                    ->label('Length')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_length')
                    ->label('Spl Length')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('core_size')
                    ->label('Core Size')
                    ->badge()
                    ->color(fn(?string $state) => empty($state) ? 'danger' : 'info')
                    ->formatStateUsing(fn(?string $state) => empty($state) ? 'Sin asignar' : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_type')
                    ->label('Spl Type')
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

                Tables\Columns\TextColumn::make('qc_photos_status')
                    ->label('Fotos QC')
                    ->badge()
                    ->state(function (DrillHoleSample $record): string {
                        if (!$record->requiresQcPhotos()) {
                            return '—';
                        }
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return "{$count}/" . DrillHoleSample::QC_PHOTOS_REQUIRED;
                    })
                    ->color(function (DrillHoleSample $record): string {
                        if (!$record->requiresQcPhotos()) {
                            return 'gray';
                        }
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return $count >= DrillHoleSample::QC_PHOTOS_REQUIRED ? 'success' : 'danger';
                    })
                    ->tooltip(function (DrillHoleSample $record): ?string {
                        if (!$record->requiresQcPhotos()) return null;
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return $count >= DrillHoleSample::QC_PHOTOS_REQUIRED ? 'Fotos completas (2/2)' : 'Faltan fotos obligatorias de control';
                    })
                    ->sortable(false),

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
                    ->limit(25)
                    ->description(fn(DrillHoleSample $record) => $record->needsLowRecoveryComment() ? '⚠️ Requiere "baja recuperacion"' : null)
                    ->color(fn(DrillHoleSample $record) => $record->needsLowRecoveryComment() ? 'warning' : null),
            ])
            ->recordActions([
                QcPhotosAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->disabled(fn(DrillHoleSample $record): bool => $record->isSent())
                    ->tooltip(fn(DrillHoleSample $record): ?string => $record->isSent()
                        ? 'Esta muestra está bloqueada: pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->form(fn() => DrillHoleSampleResource::getSampleFormSchema())
                    ->before(function (EditAction $action, DrillHoleSample $record, array $data) {
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
                    ->using(function (DrillHoleSample $record, array $data, EditAction $action): DrillHoleSample {
                        try {
                            $record->update($data);
                        } catch (\Throwable $e) {
                            // Red de seguridad: si por una condición de carrera (dos usuarios
                            // editando a la vez el mismo sample_number) o cualquier otro error
                            // inesperado falla el guardado, SIEMPRE debe quedar visible para el
                            // usuario. Nunca debe fallar en silencio.
                            //
                            // Importante: en Filament v4 los campos de una acción viven bajo el
                            // state path "mountedActions.{N}.data.*", por lo que el error debe
                            // lanzarse con esa clave completa para que se enlace visualmente al
                            // campo correspondiente (de lo contrario el error existe mas no se
                            // muestra en el formulario).
                            $isUniqueViolation = $e instanceof \Illuminate\Database\QueryException
                                && ((string) $e->getCode() === '23000' || str_contains($e->getMessage(), '1062'));

                            $errorKey = "mountedActions.{$action->getNestingIndex()}.data.sample_number";

                            if ($isUniqueViolation) {
                                $message = "El número de muestra '{$data['sample_number']}' ya existe en este proyecto. Elige otro valor.";
                            } else {
                                report($e);
                                $message = 'Ocurrió un error inesperado al guardar. Intenta nuevamente o contacta a soporte.';
                            }

                            Notification::make()
                                ->title('No se pudo guardar la muestra')
                                ->body($message)
                                ->danger()
                                ->send();

                            throw \Illuminate\Validation\ValidationException::withMessages([
                                $errorKey => $message,
                            ]);
                        }
                        return $record;
                    })
                    ->after(function (DrillHoleSample $record, SampleValidationService $service) use ($barrenoId) {
                        $service->validateDraftsForBarreno($barrenoId);
                    }),

                DeleteAction::make()
                    ->disabled(fn(DrillHoleSample $record): bool => $record->isSent())
                    ->tooltip(fn(DrillHoleSample $record): ?string => $record->isSent()
                        ? 'No se puede eliminar: esta muestra pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->after(function (SampleValidationService $service) use ($barrenoId) {
                        $service->validateDraftsForBarreno($barrenoId);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_registro')
                    ->label('Filtrar registros')
                    ->placeholder('Todos los registros')
                    ->options([
                        'gaps'                 => 'Gaps',
                        'errors'               => 'Registro con errores',
                        'originals'            => 'Registros de Originales',
                        'standards'            => 'Estandards',
                        'blanks'               => 'Blancos',
                        'duplicates'           => 'Duplicados',
                        'qc_missing_photos'    => 'QC sin fotos',
                        'low_recovery_pending' => '⚠️ Baja recuperación sin comentario',
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
                                        'qc_missing_photos' => $q->requiringQcPhotos()->missingQcPhotos(),
                                        'low_recovery_pending' => $q->lowRecoveryMissingComment(),
                                        default => null,
                                    };
                                });
                            }
                        });
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export_excel')
                        ->label('Exportar a Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records, SampleExportService $service) {
                            return $service->exportToExcel($records, $this->drillHole?->nombre_barreno);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('apply_low_recovery_comment')
                        ->label('Aplicar "baja recuperacion"')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Aplicar comentario de Baja Recuperación')
                        ->modalDescription('Se aplicará el comentario "baja recuperacion" solo a las muestras seleccionadas que tengan Sample Length < Length y comentarios vacíos. Las demás se omiten.')
                        ->modalSubmitActionLabel('Sí, aplicar')
                        ->action(function (Collection $records, SampleValidationService $service) use ($barrenoId) {
                            $eligible = $records->filter(fn(DrillHoleSample $s) => $s->needsLowRecoveryComment());

                            foreach ($eligible as $record) {
                                $record->update(['comentarios' => DrillHoleSample::LOW_RECOVERY_COMMENT]);
                            }

                            if ($eligible->isNotEmpty()) {
                                $service->validateDraftsForBarreno($barrenoId);
                            }

                            $skipped = $records->count() - $eligible->count();

                            Notification::make()
                                ->title($eligible->isNotEmpty()
                                    ? "Comentario aplicado a {$eligible->count()} muestra(s)."
                                    : 'Ninguna de las muestras seleccionadas requería el comentario.')
                                ->body($skipped > 0 ? "{$skipped} muestra(s) omitida(s) (no cumplen la condición o ya tenían comentario)." : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_work_order')
                        ->label('Asignar Work Order')
                        ->icon('heroicon-o-briefcase')
                        ->color('info')
                        ->form(function () use ($barrenoSedeId, $barrenoId) {
                            return [
                                $this->getWorkOrderSelectField($barrenoSedeId, $barrenoId),
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $targetIds = $records->pluck('id')->all();
                            $this->executeWorkOrderAssignment($targetIds, (int) $data['work_order_id']);
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action) {
                            $locked = $records->filter(fn(DrillHoleSample $s) => $s->isSent());
                            if ($locked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Operación no permitida')
                                    ->body('No se pueden eliminar muestras que pertenecen a una Work Order ya enviada al laboratorio.')
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        })
                        ->after(function (SampleValidationService $service) use ($barrenoId) {
                            $service->validateDraftsForBarreno($barrenoId);
                        }),
                ]),
            ]);
    }

    protected function getWorkOrderSelectField(?int $barrenoSedeId, int $barrenoId): Forms\Components\Select
    {
        return Forms\Components\Select::make('work_order_id')
            ->label('Work Order')
            ->options(fn() => $this->getAvailableWorkOrderOptions($barrenoSedeId, $barrenoId))
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
                    'sede_id'          => $barrenoSedeId,
                    'samples_quantity' => 0,
                ]);
                return $wo->id;
            });
    }

    protected function getAvailableWorkOrderOptions(?int $barrenoSedeId, int $barrenoId): Collection
    {
        $query = WorkOrder::query()
            ->select(['id', 'work_order_code', 'samples_quantity']);

        if ($barrenoSedeId) {
            $query->where('sede_id', $barrenoSedeId);
        }

        // Excluir órdenes ya enviadas al laboratorio
        $query->where(function ($q) {
            $q->where('sent_to_lab', false)->orWhereNull('sent_to_lab');
        });
        $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('is_archived', true));

        // Mostrar únicamente Work Orders vacías o que pertenezcan a este mismo barreno
        $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('barreno_id', '!=', $barrenoId));

        return $query->orderByDesc('id')
            ->get()
            ->mapWithKeys(
                fn($wo) => [$wo->id => "{$wo->work_order_code} (muestras: {$wo->samples_quantity})"]
            );
    }

    protected function executeWorkOrderAssignment(array $targetIds, int $workOrderId): void
    {
        $barrenoId = (int) $this->record;
        $targetIds = array_values(array_unique(array_filter($targetIds)));

        if (empty($targetIds)) {
            Notification::make()
                ->title('Sin muestras coincidentes')
                ->body('No se encontraron muestras en el criterio o rango indicado.')
                ->warning()
                ->send();
            return;
        }

        // 1. Verificar en 1 sola consulta que ninguna de las muestras seleccionadas pertenezca a una WO ya enviada
        $hasLockedSamples = DrillHoleSample::whereIn('id', $targetIds)
            ->where(function ($q) {
                $q->where('is_archived', true)
                  ->orWhereHas('workOrder', fn($wq) => $wq->where('sent_to_lab', true));
            })
            ->exists();

        if ($hasLockedSamples) {
            Notification::make()
                ->title('Operación no permitida')
                ->body('Una o más muestras seleccionadas ya pertenecen a una Work Order enviada al laboratorio y no pueden reasignarse.')
                ->danger()
                ->send();
            return;
        }

        $workOrder = WorkOrder::find($workOrderId);

        if (!$workOrder) {
            Notification::make()->title('Work Order no encontrada')->danger()->send();
            return;
        }

        // 2. Verificar que la WO destino no esté enviada
        if ($workOrder->isSent()) {
            Notification::make()
                ->title('Work Order bloqueada')
                ->body("La Work Order {$workOrder->work_order_code} ya fue enviada al laboratorio y no puede recibir más muestras.")
                ->danger()
                ->send();
            return;
        }

        // 3. Si la Work Order tiene muestras, verificar que sean del mismo barreno
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

        // 4. Validar en 1 consulta que todas las muestras seleccionadas tengan Core Size
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

            Notification::make()
                ->title('Core Size requerido')
                ->body("No se puede asignar la Work Order porque {$missingCount} muestra(s) no tienen Core Size asignado ({$sampleNumbers}...). Por favor asigna el Core Size antes de continuar.")
                ->danger()
                ->send();
            return;
        }

        // 5. Actualización masiva atómica y recálculo de conteos (sin re-ejecutar SampleValidationService)
        DB::transaction(function () use ($targetIds, $workOrder) {
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

        $this->flushCachedTableRecords();

        $assignedCount = count($targetIds);
        Notification::make()
            ->title('Work Order asignada')
            ->body("La orden {$workOrder->work_order_code} fue asignada a {$assignedCount} muestras.")
            ->success()
            ->send();
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

    #[On('qc-photos-updated')]
    public function onQcPhotosUpdated(): void
    {
        $this->flushCachedTableRecords();
    }
}
