<?php

namespace App\Filament\Resources\Proyectos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProyectoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Proyecto')
                    ->description('Registra los datos generales y la asignación de distrito del proyecto.')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Proyecto')
                            ->placeholder('Ej. Proyecto Media Luna East')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Nombre completo identificador del proyecto.')
                            ->columnSpan(1),

                        TextInput::make('code')
                            ->label('Código de Proyecto')
                            ->placeholder('Ej. MLE / PRJ-001')
                            ->required()
                            ->maxLength(30)
                            ->helperText('Clave abreviada identificadora del proyecto.')
                            ->columnSpan(1),

                        Select::make('sede_id')
                            ->label('Distrito')
                            ->placeholder('Selecciona un distrito...')
                            ->relationship('sede', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->helperText('Distrito o sede geográfica a la cual pertenece este proyecto.')
                            ->columnSpan(2),

                        Textarea::make('descripcion')
                            ->label('Descripción / Observaciones')
                            ->placeholder('Detalles adicionales sobre el proyecto, ubicación u objetivos de exploración...')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
