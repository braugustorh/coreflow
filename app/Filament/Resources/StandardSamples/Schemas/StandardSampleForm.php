<?php

namespace App\Filament\Resources\StandardSamples\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StandardSampleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles del Estándar')
                    ->description('Ingrese la información general del estándar o blanco.')
                    ->icon('heroicon-o-beaker')
                    ->columns(2)
                    ->schema([
                        TextInput::make('standard_name')
                            ->label('Nombre del Estándar')
                            ->required(),
                        TextInput::make('standard_type')
                            ->label('Tipo de Estándar')
                            ->required()
                            ->default('LABSTD'),
                        Textarea::make('comments')
                            ->label('Comentarios')
                            ->columnSpanFull(),
                        FileUpload::make('certificate_file')
                            ->label('Certificado (PDF/Imagen)')
                            ->directory('certificates')
                            ->columnSpanFull(),
                        Toggle::make('status')
                            ->label('Estado Activo')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Valores Esperados')
                    ->description('Agregue los elementos y métodos con sus valores para este estándar.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Repeater::make('standardValues')
                            ->relationship()
                            ->label('')
                            ->columns(4)
                            ->schema([
                                Select::make('element_id')
                                    ->label('Elemento')
                                    ->relationship('element', 'name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->symbol} - {$record->name}")
                                    ->searchable(['symbol', 'name'])
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('assay_method_id')
                                    ->label('Método')
                                    ->relationship('assayMethod', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('mean')
                                    ->label('Media')
                                    ->numeric(),
                                TextInput::make('min')
                                    ->label('Mínimo')
                                    ->numeric(),
                                TextInput::make('max')
                                    ->label('Máximo')
                                    ->numeric(),
                                TextInput::make('std_dev')
                                    ->label('Desv. Std.')
                                    ->numeric(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Agregar Valor'),
                    ]),
            ]);
    }
}
