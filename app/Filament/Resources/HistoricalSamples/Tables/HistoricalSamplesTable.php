<?php

namespace App\Filament\Resources\HistoricalSamples\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HistoricalSamplesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sample_number')
                    ->label('No. Muestra')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->icon('heroicon-m-hashtag'),

                TextColumn::make('proyecto.nombre')
                    ->label('Proyecto')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Global (Todos)'),

                TextColumn::make('barreno_name')
                    ->label('Barreno Original')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Fecha Carga')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('proyecto_id')
                    ->label('Proyecto')
                    ->relationship('proyecto', 'nombre')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
