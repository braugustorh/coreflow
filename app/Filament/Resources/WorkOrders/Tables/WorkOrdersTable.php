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
                    ->numeric()
                    ->sortable(),
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
