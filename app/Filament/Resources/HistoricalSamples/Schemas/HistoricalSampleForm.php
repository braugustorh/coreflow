<?php

namespace App\Filament\Resources\HistoricalSamples\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HistoricalSampleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle de Muestra Histórica')
                    ->description('Registro de folio histórico únicamente para validación de unicidad.')
                    ->icon('heroicon-o-archive-box')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sample_number')
                            ->label('Número de Muestra')
                            ->placeholder('Ej. ML-150201')
                            ->required()
                            ->maxLength(100),

                        Select::make('proyecto_id')
                            ->label('Proyecto')
                            ->placeholder('Selecciona un proyecto (opcional)...')
                            ->relationship('proyecto', 'nombre')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('barreno_name')
                            ->label('Barreno Original (Opcional)')
                            ->placeholder('Ej. DDH-001')
                            ->maxLength(100)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
