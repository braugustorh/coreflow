<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn(\App\Models\WorkOrder $record): bool => $record->isSent())
                ->tooltip(fn(\App\Models\WorkOrder $record): ?string => $record->isSent()
                    ? 'No se puede eliminar: esta Work Order ya fue enviada al laboratorio.'
                    : null
                ),
        ];
    }
}
