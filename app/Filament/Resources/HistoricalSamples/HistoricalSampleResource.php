<?php

namespace App\Filament\Resources\HistoricalSamples;

use App\Filament\Resources\HistoricalSamples\Pages\ListHistoricalSamples;
use App\Filament\Resources\HistoricalSamples\Schemas\HistoricalSampleForm;
use App\Filament\Resources\HistoricalSamples\Tables\HistoricalSamplesTable;
use App\Models\HistoricalSample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HistoricalSampleResource extends Resource
{
    protected static ?string $model = HistoricalSample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Muestras Históricas';

    protected static ?string $modelLabel = 'Muestra Histórica';

    protected static ?string $pluralModelLabel = 'Muestras Históricas';

    protected static string|\UnitEnum|null $navigationGroup = 'Control de Muestreo';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return HistoricalSampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HistoricalSamplesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricalSamples::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS', 'Geologo'])
            || $user->can('ViewAny:HistoricalSample');
    }
}
