<?php

namespace App\Filament\Resources\Sedes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class SedesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre de la Sede')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('state')
                    ->label('Estado')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                // Aquí podrías agregar filtros más adelante si lo necesitas
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(), // Agregamos el botón de eliminar por ser un catálogo
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
