<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SummarySamplesResource\Pages\ListSummarySamples;
use App\Models\WorkOrder;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SummarySamplesResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Control de Muestreo';

    protected static ?string $navigationLabel = 'Summary Samples';

    protected static ?string $modelLabel = 'Resumen de Muestra';

    protected static ?string $pluralModelLabel = 'Summary Samples';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        // Enlistar únicamente las Work Orders que tienen muestras asignadas
        return parent::getEloquentQuery()->withSamples();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Seguimiento de Recepción y Estatus de WO')
                    ->description('Captura de datos de recepción, muestras recibidas y estatus del laboratorio.')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('work_order_code')
                            ->label('Work Order')
                            ->disabled(),

                        TextInput::make('samples_quantity')
                            ->label('Total Muestras Despachadas')
                            ->disabled(),

                        DatePicker::make('dispatch_date')
                            ->label('Fecha de Envío')
                            ->native(false),

                        DatePicker::make('reception_date')
                            ->label('Fecha de Recepción')
                            ->native(false),

                        TextInput::make('received_samples_count')
                            ->label('Muestras Recibidas')
                            ->numeric()
                            ->placeholder('Ej. 50'),

                        Select::make('status')
                            ->label('Work Order Status')
                            ->options([
                                'Liberado'                 => 'Liberado',
                                'Reportado'                => 'Reportado',
                                'En revisión QaQc'         => 'En revisión QaQc',
                                'Barreno completo en Lab'  => 'Barreno completo en Lab',
                                'Barreno incompleto en Lab' => 'Barreno incompleto en Lab',
                            ])
                            ->placeholder('Selecciona un estatus...'),

                        Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Observaciones sobre el lote de muestras...')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_order_code')
                    ->label('Work Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-briefcase'),

                TextColumn::make('samples_quantity')
                    ->label('Total Muestras')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('logistics_status')
                    ->label('Estatus Logístico')
                    ->state(function (WorkOrder $record): string {
                        if (!empty($record->reception_date)) {
                            return 'Recibida';
                        }
                        if (!empty($record->dispatch_date) || $record->drillHoleSamples()->where('is_archived', true)->exists()) {
                            return 'Enviada';
                        }
                        return 'Pendiente';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Recibida'  => 'success', // Verde
                        'Enviada'   => 'info',    // Azul
                        'Pendiente' => 'warning', // Naranja
                        default     => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Recibida'  => 'heroicon-m-check-circle',
                        'Enviada'   => 'heroicon-m-paper-airplane',
                        'Pendiente' => 'heroicon-m-clock',
                        default     => 'heroicon-m-question-mark-circle',
                    })
                    ->sortable(false),

                TextColumn::make('dispatch_date')
                    ->label('Fecha de Envío')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Pendiente'),

                TextColumn::make('proyectos_list')
                    ->label('Project')
                    ->badge()
                    ->color('info')
                    ->wrap(),

                TextColumn::make('barrenos_list')
                    ->label('ID del Barreno')
                    ->weight('medium')
                    ->searchable()
                    ->wrap(),

                TextInputColumn::make('received_samples_count')
                    ->label('Muestras Recibidas')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(fn (WorkOrder $record) => ($record->received_samples_count !== null && (int) $record->received_samples_count < (int) $record->samples_quantity) ? ['style' => 'background-color: #fee2e2; border-color: #ef4444; color: #b91c1c; font-weight: bold;'] : [])
                    ->sortable(),

                TextColumn::make('reception_discrepancy')
                    ->label('Alerta Recibidas')
                    ->state(function (WorkOrder $record): ?string {
                        if ($record->received_samples_count !== null && (int) $record->received_samples_count < (int) $record->samples_quantity) {
                            $diff = (int) $record->samples_quantity - (int) $record->received_samples_count;
                            return "Faltan {$diff}";
                        }
                        if ($record->received_samples_count !== null && (int) $record->received_samples_count > (int) $record->samples_quantity) {
                            $diff = (int) $record->received_samples_count - (int) $record->samples_quantity;
                            return "+{$diff} extra";
                        }
                        return null;
                    })
                    ->badge()
                    ->color(fn ($state) => str_starts_with($state ?? '', 'Faltan') ? 'danger' : 'warning')
                    ->icon(fn ($state) => $state ? 'heroicon-m-exclamation-triangle' : null)
                    ->placeholder('—')
                    ->alignCenter(),

                TextInputColumn::make('reception_date')
                    ->label('Fecha Recepción')
                    ->type('date')
                    ->rules(['nullable', 'date'])
                    ->extraInputAttributes(['onclick' => 'try { this.showPicker(); } catch (e) {}', 'class' => 'cursor-pointer'])
                    ->getStateUsing(fn (WorkOrder $record) => $record->reception_date?->format('Y-m-d'))
                    ->sortable(),

                SelectColumn::make('status')
                    ->label('Status WO')
                    ->options([
                        'Liberado'                 => 'Liberado',
                        'Reportado'                => 'Reportado',
                        'En revisión QaQc'         => 'En revisión QaQc',
                        'Barreno completo en Lab'  => 'Barreno completo en Lab',
                        'Barreno incompleto en Lab' => 'Barreno incompleto en Lab',
                    ])
                    ->placeholder('Seleccionar...'),

                TextColumn::make('turnaround_time')
                    ->label('TURNAROUND_TIME')
                    ->state(fn (WorkOrder $record) => $record->turnaround_time !== null ? "{$record->turnaround_time} días" : 'N/D')
                    ->badge()
                    ->color(fn (WorkOrder $record) => match (true) {
                        $record->turnaround_time === null => 'gray',
                        $record->turnaround_time <= 7    => 'success',
                        $record->turnaround_time <= 15   => 'warning',
                        default                          => 'danger',
                    })
                    ->alignCenter(),

                TextInputColumn::make('comments')
                    ->label('Comentarios')
                    ->placeholder('Agregar comentario...'),

                TextColumn::make('original_samples_count')
                    ->label('Original Samples')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('qc_samples_count')
                    ->label('QC Samples (Standards)')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('pulp_samples_count')
                    ->label('PULP Samples (Blanks)')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar Recepción')
                    ->icon('heroicon-o-pencil-square')
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSummarySamples::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS']);
    }
}
