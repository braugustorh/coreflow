<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Table;
use Filament\Actions\Action;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('sede.name')->label('Sede')->sortable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('activities')
                    ->label('Historial')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->color('info')
                    ->url(fn($record) => UserResource::getUrl('activities', ['record' => $record])),
            ]);
    }
}
