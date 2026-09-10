<?php

namespace App\Filament\Resources\AssayMethods;

use App\Filament\Resources\AssayMethods\Pages\CreateAssayMethod;
use App\Filament\Resources\AssayMethods\Pages\EditAssayMethod;
use App\Filament\Resources\AssayMethods\Pages\ListAssayMethods;
use App\Filament\Resources\AssayMethods\Schemas\AssayMethodForm;
use App\Filament\Resources\AssayMethods\Tables\AssayMethodsTable;
use App\Models\AssayMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssayMethodResource extends Resource
{
    protected static ?string $model = AssayMethod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-funnel';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AssayMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssayMethodsTable::configure($table);
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
            'index' => ListAssayMethods::route('/'),
            'create' => CreateAssayMethod::route('/create'),
            'edit' => EditAssayMethod::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow']);
    }
}
