<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_order_code')
                    ->searchable(),
                TextColumn::make('sede.name')
                    ->label('Distrito')
                    ->searchable(),
                TextColumn::make('samples_quantity')
                    ->label('Cant. Muestras')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'Liberado' => 'success',
                        'Reportado' => 'info',
                        'En revisión QaQc' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('Pendiente')
                    ->sortable(),
                TextColumn::make('reception_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('comments')
                    ->label('Observaciones')
                    ->badge(fn (?string $state) => !empty($state) && str_contains($state, 'Histórico'))
                    ->color(fn (?string $state) => (!empty($state) && str_contains($state, 'Histórico')) ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),

                \Filament\Actions\Action::make('export_als')
                    ->label('Formulario ALS (PDF)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (WorkOrder $record, \App\Services\AlsFormFillService $service) {
                        return $service->generate($record);
                    }),

                \Filament\Actions\Action::make('export_sacks')
                    ->label('Registro Costales (Excel)')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function (WorkOrder $record, \App\Services\SackFormGeneratorService $service) {
                        try {
                            return $service->generate($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al generar Registro de Costales')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return null;
                        }
                    }),

                \Filament\Actions\Action::make('release_samples')
                    ->label('Desasociar muestras')
                    ->icon('heroicon-o-link-slash')
                    ->color('warning')
                    ->visible(fn (WorkOrder $record): bool => WorkOrder::userCanManageAssociations()
                        && $record->drillHoleSamples()->exists())
                    ->disabled(fn (WorkOrder $record): bool => $record->getAssociationLockReason() !== null)
                    ->tooltip(fn (WorkOrder $record): ?string => $record->getAssociationLockReason())
                    ->requiresConfirmation()
                    ->modalHeading(fn (WorkOrder $record): string => "Desasociar muestras de la WO {$record->work_order_code}")
                    ->modalDescription(function (WorkOrder $record): string {
                        $count = $record->drillHoleSamples()->count();

                        return "Se desasociarán {$count} muestra(s) de esta Work Order. Las muestras NO se eliminan: conservan todos sus datos y quedan sin Work Order asignada para poder reasignarlas. La Work Order se mantiene con 0 muestras.";
                    })
                    ->modalSubmitActionLabel('Sí, desasociar muestras')
                    ->action(function (WorkOrder $record): void {
                        if ($reason = $record->getAssociationLockReason()) {
                            Notification::make()
                                ->title('Operación no permitida')
                                ->body($reason)
                                ->danger()
                                ->send();

                            return;
                        }

                        $count = $record->releaseSamples();

                        Notification::make()
                            ->title('Muestras desasociadas')
                            ->body("{$count} muestra(s) quedaron sin Work Order asignada.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => WorkOrder::userCanManageAssociations())
                        ->modalHeading('Eliminar Work Orders seleccionadas')
                        ->modalDescription(function (\Illuminate\Support\Collection $records): string {
                            $withSamples = $records->filter(fn(WorkOrder $wo) => $wo->drillHoleSamples()->exists());
                            if ($withSamples->isNotEmpty()) {
                                $totalSamples = DrillHoleSample::whereIn('work_order_id', $withSamples->pluck('id'))->count();
                                $codes = $withSamples->pluck('work_order_code')->implode(', ');
                                return "⚠️ ATENCIÓN: Las órdenes ({$codes}) tienen un total de {$totalSamples} muestra(s) asignada(s). Al eliminarlas, desaparecerán del Monitor de Muestreo y esas {$totalSamples} muestras quedarán desvinculadas (sin Work Order). ¿Confirmas que deseas continuar?";
                            }

                            return '¿Estás seguro de que deseas eliminar las Work Orders seleccionadas?';
                        })
                        ->before(function (\Illuminate\Support\Collection $records, \Filament\Actions\DeleteBulkAction $action) {
                            $locked = $records->filter(fn(WorkOrder $wo) => $wo->getAssociationLockReason() !== null);
                            if ($locked->isNotEmpty()) {
                                $codes = $locked->pluck('work_order_code')->implode(', ');
                                \Filament\Notifications\Notification::make()
                                    ->title('Operación no permitida')
                                    ->body("No se pueden eliminar las Work Orders ({$codes}): ya fueron enviadas, recibidas, completadas o liberadas.")
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
