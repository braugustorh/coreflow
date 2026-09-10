<?php

namespace App\Filament\Resources\Elements;

use App\Filament\Resources\Elements\Pages\CreateElement;
use App\Filament\Resources\Elements\Pages\EditElement;
use App\Filament\Resources\Elements\Pages\ListElements;
use App\Filament\Resources\Elements\Schemas\ElementForm;
use App\Filament\Resources\Elements\Tables\ElementsTable;
use App\Models\Element;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ElementResource extends Resource
{
    protected static ?string $model = Element::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ElementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ElementsTable::configure($table);
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
            'index' => ListElements::route('/'),
            'create' => CreateElement::route('/create'),
            'edit' => EditElement::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow']);
    }
}
