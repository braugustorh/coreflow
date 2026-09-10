<?php

namespace App\Filament\Resources\SamplingMonitor;

use App\Filament\Resources\SamplingMonitor\Pages\ListSamplingMonitor;
use App\Filament\Resources\SamplingMonitor\Tables\SamplingMonitorTable;
use App\Models\WorkOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SamplingMonitorResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Monitor de Muestreo';

    protected static ?string $modelLabel = 'Orden de Trabajo';

    protected static ?string $pluralModelLabel = 'Monitor de Muestreo';

    // navigationGroup inherited type is UnitEnum|string|null — set via method override
    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Control de Muestreo';
    }

    /**
     * No exponemos formulario Create/Edit desde este Resource.
     * La edición de campos logísticos se hace inline o via slideOver en la tabla.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SamplingMonitorTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSamplingMonitor::route('/'),
        ];
    }

    /**
     * Desactivar la página de creación y edición dedicada.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS']);
    }
}
