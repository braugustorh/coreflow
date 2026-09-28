<?php

namespace App\Filament\Resources\SamplingMonitor\Tables;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use App\Services\AlsFormFillService;
use App\Services\GapCalculatorService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SamplingMonitorTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                // Query base sobre WorkOrders que tienen muestras asignadas
                WorkOrder::query()
                    ->withMin('drillHoleSamples', 'from_depth')
                    ->withMax('drillHoleSamples', 'to_depth')
                    ->withSum('drillHoleSamples', 'weight')
                    ->withMin('drillHoleSamples', 'sample_number')
                    ->withMax('drillHoleSamples', 'sample_number')
                    ->whereHas('drillHoleSamples')
            )
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->columns([
                // ── Datos de identificación ──────────────────────────────────

                TextColumn::make('proyecto_nombre')
                    ->label('Proyecto')
                    ->getStateUsing(
                        fn(WorkOrder $record): string =>
                        $record->drillHoleSamples()
                            ->with('proyecto')
                            ->first()?->proyecto?->nombre ?? '—'
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'drillHoleSamples.proyecto',
                            fn($q) =>
                            $q->where('nombre', 'like', "%{$search}%")
                        );
                    })
                    ->sortable(
                        query: fn(Builder $query, string $direction): Builder =>
                        $query->orderBy(
                            DrillHoleSample::select('proyectos.nombre')
                                ->join('proyectos', 'proyectos.id', '=', 'drill_hole_samples.proyecto_id')
                                ->whereColumn('drill_hole_samples.work_order_id', 'work_orders.id')
                                ->limit(1),
                            $direction
                        )
                    )
                    ->icon('heroicon-o-map')
                    ->weight('medium'),

                TextColumn::make('barreno_nombre')
                    ->label('Barreno')
                    ->getStateUsing(
                        fn(WorkOrder $record): string =>
                        $record->drillHoleSamples()
                            ->with('barreno')
                            ->first()?->barreno?->nombre_barreno ?? '—'
                    )
                    ->badge()
                    ->color('info'),

                TextColumn::make('work_order_code')
                    ->label('Work Order')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->copyable()
                    ->weight('bold'),

                // ── Métricas agregadas ──────────────────────────────────────

                TextColumn::make('samples_quantity')
                    ->label('# Muestras')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-beaker'),

                TextColumn::make('drill_hole_samples_sum_weight')
                    ->label('Peso Total (kg)')
                    ->numeric(2)
                    ->sortable()
                    ->suffix(' kg')
                    ->color('gray'),

                // ── Rangos geológicos ────────────────────────────────────────

                TextColumn::make('drill_hole_samples_min_from_depth')
                    ->label('Prof. Inicial (m)')
                    ->numeric(2)
                    ->sortable()
                    ->suffix(' m')
                    ->color('gray'),

                TextColumn::make('drill_hole_samples_max_to_depth')
                    ->label('Prof. Final (m)')
                    ->numeric(2)
                    ->sortable()
                    ->suffix(' m')
                    ->color('gray'),

                // ── Rango de etiquetas ───────────────────────────────────────

                TextColumn::make('drill_hole_samples_min_sample_number')
                    ->label('Muestra Inicio')
                    ->searchable(
                        query: fn(Builder $q, string $s) =>
                        $q->whereHas(
                            'drillHoleSamples',
                            fn($sq) =>
                            $sq->where('sample_number', 'like', "%{$s}%")
                        )
                    )
                    ->badge()
                    ->color('gray'),

                TextColumn::make('drill_hole_samples_max_sample_number')
                    ->label('Muestra Fin')
                    ->badge()
                    ->color('gray'),

                // ── Material ─────────────────────────────────────────────────

                TextColumn::make('core_size_display')
                    ->label('Material')
                    ->getStateUsing(
                        fn(WorkOrder $record): string =>
                        $record->drillHoleSamples()
                            ->whereNotNull('core_size')
                            ->value('core_size') ?? '—'
                    )
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PQ' => 'info',
                        'HQ' => 'success',
                        'NQ' => 'warning',
                        'BQ' => 'danger',
                        default => 'gray',
                    }),

                // ── Campos logísticos editables inline ───────────────────────
                // Nota: estos campos viven en drill_hole_samples pero se editan
                // a nivel de WorkOrder (actualizan todas las muestras de la WO).
                // Para lograrlo, usamos columnas que leen el valor del primer
                // registro y guardan en todos via observer/evento en el modelo.
                // El modelo de la tabla es WorkOrder, así que usamos
                // un campo virtual sincronizado:

                SelectColumn::make('hole_status_display')
                    ->label('Estado del Barreno')
                    ->options([
                        'Ongoing Sampling' => 'Ongoing Sampling',
                        'End of Hole' => 'End of Hole',
                    ])
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        $record->drillHoleSamples()->value('hole_status')
                    )
                    ->updateStateUsing(function (WorkOrder $record, ?string $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['hole_status' => $state]);
                    })
                    ->sortable(
                        query: fn(Builder $q, string $dir): Builder =>
                        $q->orderBy(
                            DrillHoleSample::select('hole_status')
                                ->whereColumn('work_order_id', 'work_orders.id')
                                ->limit(1),
                            $dir
                        )
                    ),

                TextColumn::make('sent_date_display')
                    ->label('Fecha de Envío')
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        optional($record->drillHoleSamples()->value('sent_date'))
                        ? \Carbon\Carbon::parse($record->drillHoleSamples()->value('sent_date'))
                            ->format('d/m/Y')
                        : '—'
                    )
                    ->placeholder('—'),

                SelectColumn::make('bags_sacks_status_display')
                    ->label('Estado Bolsas/Sacos')
                    ->options([
                        'Printed' => 'Impreso',
                        'In Process' => 'En Proceso',
                        'Completed' => 'Completado',
                    ])
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        $record->drillHoleSamples()->value('bags_sacks_status')
                    )
                    ->updateStateUsing(function (WorkOrder $record, ?string $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['bags_sacks_status' => $state]);
                    }),

                SelectColumn::make('responsible_supervisor_display')
                    ->label('Supervisor')
                    ->options([
                        'MF' => 'MF',
                        'EC' => 'EC',
                    ])
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        $record->drillHoleSamples()->value('responsible_supervisor')
                    )
                    ->updateStateUsing(function (WorkOrder $record, ?string $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['responsible_supervisor' => $state]);
                    }),

                SelectColumn::make('sampling_status_display')
                    ->label('Estado Muestreo')
                    ->options([
                        'Pending' => 'Pendiente',
                        'In Progress' => 'En Progreso',
                        'Completed' => 'Completado',
                    ])
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        $record->drillHoleSamples()->value('sampling_status')
                    )
                    ->updateStateUsing(function (WorkOrder $record, ?string $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['sampling_status' => $state]);
                    }),

                ToggleColumn::make('rush_display')
                    ->label('RUSH')
                    ->getStateUsing(
                        fn(WorkOrder $record): bool =>
                        (bool) $record->drillHoleSamples()->value('rush')
                    )
                    ->updateStateUsing(function (WorkOrder $record, bool $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['rush' => $state]);
                    }),

                TextInputColumn::make('comentarios_display')
                    ->label('Comentarios')
                    ->getStateUsing(
                        fn(WorkOrder $record): ?string =>
                        $record->drillHoleSamples()->value('comentarios')
                    )
                    ->updateStateUsing(function (WorkOrder $record, ?string $state): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['comentarios' => $state]);
                    })
                    ->placeholder('Agregar nota...'),
            ])

            // ── Filtros ───────────────────────────────────────────────────────

            ->filters([
                SelectFilter::make('sampling_status')
                    ->label('Estado de Muestreo')
                    ->options([
                        'Pending' => 'Pendiente',
                        'In Progress' => 'En Progreso',
                        'Completed' => 'Completado',
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        isset($data['value'])
                        ? $query->whereHas(
                            'drillHoleSamples',
                            fn($q) =>
                            $q->where('sampling_status', $data['value'])
                        )
                        : $query
                    ),

                SelectFilter::make('hole_status')
                    ->label('Estado del Barreno')
                    ->options([
                        'Ongoing Sampling' => 'Ongoing Sampling',
                        'End of Hole' => 'End of Hole',
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        isset($data['value'])
                        ? $query->whereHas(
                            'drillHoleSamples',
                            fn($q) =>
                            $q->where('hole_status', $data['value'])
                        )
                        : $query
                    ),

                SelectFilter::make('bags_sacks_status')
                    ->label('Estado Bolsas/Sacos')
                    ->options([
                        'Printed' => 'Impreso',
                        'In Process' => 'En Proceso',
                        'Completed' => 'Completado',
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        isset($data['value'])
                        ? $query->whereHas(
                            'drillHoleSamples',
                            fn($q) =>
                            $q->where('bags_sacks_status', $data['value'])
                        )
                        : $query
                    ),

                SelectFilter::make('responsible_supervisor')
                    ->label('Supervisor')
                    ->options([
                        'MF' => 'MF',
                        'EC' => 'EC',
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        isset($data['value'])
                        ? $query->whereHas(
                            'drillHoleSamples',
                            fn($q) =>
                            $q->where('responsible_supervisor', $data['value'])
                        )
                        : $query
                    ),
            ])

            // ── Acciones por fila ─────────────────────────────────────────────

            ->recordActions([
                Action::make('edit_logistics')
                    ->label('Editar Logística')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->slideOver()
                    ->fillForm(function (WorkOrder $record): array {
                        $sample = $record->drillHoleSamples()->first();
                        return [
                            'hole_status' => $sample?->hole_status,
                            'sent_date' => $sample?->sent_date?->format('Y-m-d'),
                            'bags_sacks_status' => $sample?->bags_sacks_status,
                            'responsible_supervisor' => $sample?->responsible_supervisor,
                            'sampling_status' => $sample?->sampling_status ?? 'Pending',
                            'rush' => (bool) ($sample?->rush ?? false),
                            'comentarios' => $sample?->comentarios,
                        ];
                    })
                    ->form([
                        \Filament\Forms\Components\Select::make('hole_status')
                            ->label('Estado del Barreno')
                            ->options([
                                'Ongoing Sampling' => 'Ongoing Sampling',
                                'End of Hole' => 'End of Hole',
                            ]),
                        \Filament\Forms\Components\DatePicker::make('sent_date')
                            ->label('Fecha de Envío')
                            ->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\Select::make('bags_sacks_status')
                            ->label('Estado Bolsas/Sacos')
                            ->options([
                                'Printed' => 'Impreso',
                                'In Process' => 'En Proceso',
                                'Completed' => 'Completado',
                            ]),
                        \Filament\Forms\Components\Select::make('responsible_supervisor')
                            ->label('Supervisor Responsable')
                            ->options([
                                'MF' => 'MF',
                                'EC' => 'EC',
                            ]),
                        \Filament\Forms\Components\Select::make('sampling_status')
                            ->label('Estado de Muestreo')
                            ->options([
                                'Pending' => 'Pendiente',
                                'In Progress' => 'En Progreso',
                                'Completed' => 'Completado',
                            ]),
                        \Filament\Forms\Components\Toggle::make('rush')
                            ->label('RUSH')
                            ->inline(false),
                        \Filament\Forms\Components\Textarea::make('comentarios')
                            ->label('Comentarios')
                            ->rows(3),
                    ])
                    ->action(function (WorkOrder $record, array $data): void {
                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update([
                                'hole_status' => $data['hole_status'] ?? null,
                                'sent_date' => $data['sent_date'] ?? null,
                                'bags_sacks_status' => $data['bags_sacks_status'] ?? null,
                                'responsible_supervisor' => $data['responsible_supervisor'] ?? null,
                                'sampling_status' => $data['sampling_status'] ?? 'Pending',
                                'rush' => $data['rush'] ?? false,
                                'comentarios' => $data['comentarios'] ?? null,
                            ]);

                        $record->update([
                            'dispatch_date' => $data['sent_date'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Logística actualizada')
                            ->body("WO {$record->work_order_code} actualizada correctamente.")
                            ->success()
                            ->send();
                    }),

                Action::make('export_als')
                    ->label('Formulario ALS')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Generar Formulario ALS')
                    ->modalDescription('Se generará el PDF del formulario ALS Geochemistry con las muestras de esta Work Order.')
                    ->modalSubmitActionLabel('Generar PDF')
                    ->action(function (WorkOrder $record, AlsFormFillService $service) {
                        return $service->generate($record);
                    }),

                Action::make('export_sacks')
                    ->label('Costales')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Registro de Costales (Excel)')
                    ->modalDescription('Se generará el reporte Excel de Registro de Costales para el despacho al laboratorio Bureau Veritas.')
                    ->modalSubmitActionLabel('Descargar Excel')
                    ->action(function (WorkOrder $record, \App\Services\SackFormGeneratorService $service) {
                        return $service->generate($record);
                    }),

                Action::make('send_order')
                    ->label(fn (WorkOrder $record): string => (bool) $record->drillHoleSamples()->value('is_archived') ? 'Devolver' : 'Enviar')
                    ->icon(fn (WorkOrder $record): string => (bool) $record->drillHoleSamples()->value('is_archived') ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-paper-airplane')
                    ->color(fn (WorkOrder $record): string => (bool) $record->drillHoleSamples()->value('is_archived') ? 'gray' : 'success')
                    ->modalHeading(function (WorkOrder $record): string {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');
                        if ($isArchived) {
                            return "Devolver WO {$record->work_order_code} a Por Enviar";
                        }

                        $missing = self::getMissingSendRequirements($record);
                        if (!empty($missing)) {
                            return "Requisitos Incompletos - WO {$record->work_order_code}";
                        }

                        $sentDate = $record->drillHoleSamples()->whereNotNull('sent_date')->value('sent_date');
                        return empty($sentDate)
                            ? "Enviar Orden - WO {$record->work_order_code}"
                            : "Confirmar Envío - WO {$record->work_order_code}";
                    })
                    ->modalDescription(function (WorkOrder $record): ?string {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');
                        if ($isArchived) {
                            return "¿Deseas regresar esta orden de trabajo a la pestaña 'Por Enviar'?";
                        }

                        $missing = self::getMissingSendRequirements($record);
                        if (!empty($missing)) {
                            return "No es posible enviar la orden al laboratorio porque faltan datos obligatorios en la tabla.";
                        }

                        $sentDate = $record->drillHoleSamples()->whereNotNull('sent_date')->value('sent_date');
                        if (empty($sentDate)) {
                            return "No existe una fecha capturada de envío para esta orden de trabajo. Se sugiere asignar la fecha actual (" . now()->format('d/m/Y') . ") como fecha de envío. Si no es correcto, puedes seleccionar otra fecha o cancelar para asignarla manualmente.";
                        }

                        $dateFormatted = Carbon::parse($sentDate)->format('d/m/Y');
                        $supervisor = $record->drillHoleSamples()->whereNotNull('responsible_supervisor')->value('responsible_supervisor');
                        return "Se marcará la orden {$record->work_order_code} como enviada al laboratorio con fecha de envío {$dateFormatted} y supervisor {$supervisor}.";
                    })
                    ->modalSubmitAction(function ($action, WorkOrder $record) {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');
                        if ($isArchived) {
                            return $action;
                        }

                        $missing = self::getMissingSendRequirements($record);
                        return !empty($missing) ? false : $action;
                    })
                    ->modalSubmitActionLabel(function (WorkOrder $record): string {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');
                        if ($isArchived) {
                            return "Sí, Devolver";
                        }

                        $sentDate = $record->drillHoleSamples()->whereNotNull('sent_date')->value('sent_date');
                        return empty($sentDate) ? "Asignar Fecha y Enviar" : "Confirmar Envío";
                    })
                    ->form(function (WorkOrder $record): array {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');
                        if ($isArchived) {
                            return [];
                        }

                        $missing = self::getMissingSendRequirements($record);
                        if (!empty($missing)) {
                            $listHtml = '<ul>' . implode('', array_map(fn ($m) => "<li>• {$m}</li>", $missing)) . '</ul>';
                            return [
                                Placeholder::make('missing_warning')
                                    ->label('Campos Pendientes Requeridos')
                                    ->content(new HtmlString('<div style="padding: 14px 16px; background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; font-size: 13px; line-height: 1.5;"><strong>Atención:</strong> Para poder enviar esta orden al laboratorio, primero debes capturar los siguientes campos en la fila correspondiente:<div style="margin-top: 8px; font-weight: 600;">' . $listHtml . '</div><p style="margin-top: 8px; font-size: 12px; color: #b91c1c;">Por favor cierra este diálogo, selecciona los valores faltantes en la tabla y vuelve a intentar el envío.</p></div>')),
                            ];
                        }

                        $sentDate = $record->drillHoleSamples()->whereNotNull('sent_date')->value('sent_date');
                        if (!empty($sentDate)) {
                            return [];
                        }

                        return [
                            DatePicker::make('sent_date')
                                ->label('Fecha de Envío')
                                ->default(now()->format('Y-m-d'))
                                ->required()
                                ->native(false)
                                ->helperText('Por defecto se asigna la fecha de hoy. Puedes cambiarla o cancelar si deseas capturarla después.'),
                        ];
                    })
                    ->action(function (WorkOrder $record, array $data, Action $action): void {
                        $isArchived = (bool) $record->drillHoleSamples()->value('is_archived');

                        if ($isArchived) {
                            DrillHoleSample::where('work_order_id', $record->id)
                                ->update(['is_archived' => false, 'sent_date' => null]);

                            $record->update(['sent_to_lab' => false, 'dispatch_date' => null]);

                            Notification::make()
                                ->title('Orden devuelta a Por Enviar')
                                ->body("La WO {$record->work_order_code} fue devuelta a la lista 'Por Enviar'.")
                                ->info()
                                ->send();
                            return;
                        }

                        $missing = self::getMissingSendRequirements($record);
                        if (!empty($missing)) {
                            Notification::make()
                                ->title('Requisitos incompletos')
                                ->body('Faltan campos obligatorios para enviar la orden: ' . implode(', ', $missing))
                                ->danger()
                                ->send();
                            return;
                        }

                        $sentDate = $data['sent_date'] ?? ($action->getArguments()['sent_date'] ?? null);
                        if (!empty($sentDate)) {
                            DrillHoleSample::where('work_order_id', $record->id)
                                ->update(['sent_date' => $sentDate]);

                            $record->update(['dispatch_date' => $sentDate]);
                        }

                        DrillHoleSample::where('work_order_id', $record->id)
                            ->update(['is_archived' => true]);

                        // Marcar la WO como enviada (fuente de verdad para restricciones de edición)
                        $record->update(['sent_to_lab' => true]);

                        Notification::make()
                            ->title('Orden Enviada al Laboratorio')
                            ->body("La WO {$record->work_order_code} se ha marcado como enviada y se movió a la pestaña 'Enviados'.")
                            ->success()
                            ->send();
                    }),
            ])

            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([]),
            ]);
    }

    /**
     * Revisa si faltan requisitos obligatorios para enviar la orden:
     * - Supervisor Responsable y Estado de Muestreo.
     * - GAPs sin validar en los barrenos de la orden.
     * - Errores de captura sin corregir (traslapes, profundidades inválidas, errores en BD).
     * - Core Size capturado en las muestras originales.
     */
    public static function getMissingSendRequirements(WorkOrder $record): array
    {
        $missing = [];

        $supervisor = $record->drillHoleSamples()->whereNotNull('responsible_supervisor')->value('responsible_supervisor');
        if (empty($supervisor)) {
            $missing[] = 'Supervisor Responsable';
        }

        $samplingStatus = $record->drillHoleSamples()->whereNotNull('sampling_status')->value('sampling_status');
        if (empty($samplingStatus)) {
            $missing[] = 'Estado de Muestreo';
        }

        // Cargar muestras con barreno para validaciones técnicas
        $samples = $record->drillHoleSamples()->with('barreno')->get();

        if ($samples->isEmpty()) {
            $missing[] = 'La orden no contiene muestras';
            return $missing;
        }

        // 1. Validar Core Size en muestras originales
        $missingCoreSizeCount = $samples->filter(function ($s) {
            $isControl = strtolower((string) $s->sample_type) === 'control' || !empty($s->control_type);
            if ($isControl) {
                return false;
            }
            $cs = $s->core_size ?: $s->barreno?->core_size;
            return empty($cs);
        })->count();

        if ($missingCoreSizeCount > 0) {
            $missing[] = "Core Size faltante en {$missingCoreSizeCount} muestra(s) original(es)";
        }

        // 2. Validar que no haya errores pendientes en la BD
        $samplesWithErrors = $samples->filter(function ($s) {
            return !empty($s->errors) && is_array($s->errors) && count($s->errors) > 0;
        });

        if ($samplesWithErrors->isNotEmpty()) {
            $errCount = $samplesWithErrors->count();
            $missing[] = "{$errCount} muestra(s) tienen errores de validación sin corregir";
        }

        // 3. Validar GAPs sin validar y traslapes por barreno
        $barrenos = $samples->pluck('barreno')->filter()->unique('id');

        foreach ($barrenos as $barreno) {
            $barrenoSamples = $samples->where('barreno_id', $barreno->id)
                ->filter(fn($s) => strtolower((string)$s->sample_type) !== 'control' && empty($s->control_type));

            $minFrom = $barrenoSamples->whereNotNull('from_depth')->min('from_depth');
            $maxTo   = $barrenoSamples->whereNotNull('to_depth')->max('to_depth');

            // Verificar GAPs no validados en el tramo de la orden
            $gaps = GapCalculatorService::calculateGaps($barreno);
            if (!empty($gaps) && $minFrom !== null && $maxTo !== null) {
                $intersectingGaps = [];
                foreach ($gaps as $g) {
                    if ($g['from_depth'] < $maxTo && $g['to_depth'] > $minFrom) {
                        $intersectingGaps[] = "{$g['from_depth']}m - {$g['to_depth']}m";
                    }
                }

                if (!empty($intersectingGaps)) {
                    $gapsStr = implode(', ', array_slice($intersectingGaps, 0, 3));
                    $missing[] = "GAPs sin validar en barreno {$barreno->nombre_barreno} ({$gapsStr})";
                }
            }

            // Verificar traslapes de profundidad
            $sortedSamples = $barrenoSamples
                ->filter(fn($s) => $s->from_depth !== null && $s->to_depth !== null)
                ->sortBy('from_depth')
                ->values();

            $prevTo = null;
            foreach ($sortedSamples as $s) {
                $from = (float) $s->from_depth;
                $to   = (float) $s->to_depth;

                if ($from >= $to) {
                    $missing[] = "Muestra {$s->sample_number}: Profundidad inválida (FROM {$from}m >= TO {$to}m)";
                    break;
                }

                if ($prevTo !== null && $from < ($prevTo - 0.001)) {
                    $missing[] = "Traslape en barreno {$barreno->nombre_barreno}: Muestra {$s->sample_number} (FROM {$from}m < TO anterior {$prevTo}m)";
                    break;
                }

                $prevTo = max($prevTo ?? 0, $to);
            }
        }

        return $missing;
    }
}
