<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles de la Orden de Trabajo')
                    ->description('Información general de la orden de trabajo (WO).')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        TextInput::make('work_order_code')
                            ->label('Código de WO (6 dígitos)')
                            ->numeric()
                            ->length(6)
                            ->rules(['digits:6'])
                            ->unique('work_orders', 'work_order_code', ignoreRecord: true)
                            ->placeholder('Ej: 260801')
                            ->required(),
                        Select::make('sede_id')
                            ->label('Distrito')
                            ->relationship('sede', 'name')
                            ->required(),
                        TextInput::make('samples_quantity')
                            ->label('Cantidad de Muestras')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
