<?php

namespace App\Filament\Resources\Elements\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ElementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('symbol')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
