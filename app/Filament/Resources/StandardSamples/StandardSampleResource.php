<?php

namespace App\Filament\Resources\StandardSamples;

use App\Filament\Resources\StandardSamples\Pages\CreateStandardSample;
use App\Filament\Resources\StandardSamples\Pages\EditStandardSample;
use App\Filament\Resources\StandardSamples\Pages\ListStandardSamples;
use App\Filament\Resources\StandardSamples\Schemas\StandardSampleForm;
use App\Filament\Resources\StandardSamples\Tables\StandardSamplesTable;
use App\Models\StandardSample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StandardSampleResource extends Resource
{
    protected static ?string $model = StandardSample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    public static function form(Schema $schema): Schema
    {
        return StandardSampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StandardSamplesTable::configure($table);
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
            'index' => ListStandardSamples::route('/'),
            'create' => CreateStandardSample::route('/create'),
            'edit' => EditStandardSample::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS'])
            || $user->can('ViewAny:StandardSample');
    }
}
