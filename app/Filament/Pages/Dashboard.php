<?php

namespace App\Filament\Pages;

use App\Models\Proyecto;
use App\Models\Sede;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Support\Facades\Auth;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('sede_id')
                    ->label('Distrito (Sede)')
                    ->options(Sede::pluck('name', 'id'))
                    ->default(fn () => Auth::user()->sede_id)
                    ->live()
                    ->disabled(fn () => !Auth::user()->hasAnyRole(['super_admin', 'Administrador de Coreshack', 'coreshack_admin']))
                    ->dehydrated(),
                
                Select::make('proyecto_id')
                    ->label('Proyecto')
                    ->options(fn (callable $get) => 
                        $get('sede_id') 
                            ? Proyecto::where('sede_id', $get('sede_id'))->pluck('nombre', 'id') 
                            : Proyecto::pluck('nombre', 'id')
                    )
                    ->placeholder('Todos los proyectos')
                    ->live(),
            ])
            ->columns(2);
    }
}
