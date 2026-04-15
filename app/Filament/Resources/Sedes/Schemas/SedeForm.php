<?php

namespace App\Filament\Resources\Sedes\Schemas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Schema;

class SedeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Envolvemos todo en una "Card" visual (Section)
                \Filament\Schemas\Components\Section::make('Detalles del Centro de Trabajo')
                    ->description('Ingresa la información básica y ubicación operativa de la sede.')
                    ->icon('heroicon-o-building-office-2') // Un icono elegante para el encabezado
                    ->schema([
                        // El nombre ocupa todo el ancho de la tarjeta
                        TextInput::make('name')
                            ->label('Nombre de la Sede')
                            ->placeholder('Ej. Oficina Central de la Mina')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        // Agrupamos la ubicación en 3 columnas perfectas
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('country')
                                    ->label('País')
                                    ->default('México') // Pre-llenado para ahorrar clics
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('state')
                                    ->label('Estado')
                                    ->default('Guanajuato') // Automatizamos lo más común
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('Ej. Guanajuato')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible() // Permite al usuario minimizar la tarjeta si hay más cosas en la pantalla
                    ->compact(), // Reduce los márgenes internos para que se vea más estilizado
            ]);
    }
}
