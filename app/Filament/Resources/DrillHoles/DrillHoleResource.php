<?php

namespace App\Filament\Resources\DrillHoles;

use App\Filament\Resources\DrillHoles\Pages\CreateDrillHole;
use App\Filament\Resources\DrillHoles\Pages\EditDrillHole;
use App\Filament\Resources\DrillHoles\Pages\ListDrillHoles;
use App\Filament\Resources\DrillHoles\Schemas\DrillHoleForm;
use App\Filament\Resources\DrillHoles\Tables\DrillHolesTable;
use App\Models\DrillHole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DrillHoleResource extends Resource
{
    protected static ?string $model = DrillHole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    public static function form(Schema $schema): Schema
    {
        return DrillHoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrillHolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrillHoles::route('/'),
            'create' => CreateDrillHole::route('/create'),
            'edit' => EditDrillHole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS', 'Geologo'])
            || $user->can('ViewAny:DrillHole');
    }
}
