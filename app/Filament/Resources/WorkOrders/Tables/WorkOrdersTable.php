<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                        return $service->generate($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (\Illuminate\Support\Collection $records, \Filament\Actions\DeleteBulkAction $action) {
                            $sent = $records->filter(fn(WorkOrder $wo) => $wo->isSent());
                            if ($sent->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Operación no permitida')
                                    ->body('No se pueden eliminar Work Orders que ya fueron enviadas al laboratorio.')
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
