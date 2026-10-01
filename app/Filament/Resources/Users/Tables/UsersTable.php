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
                Tables\Columns\TextColumn::make('code')->label('CODE')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user')->label('Usuario')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('sede.name')->label('Distrito')->sortable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('resetPassword')
                    ->label('Restablecer Contraseña')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->modalHeading(fn (\App\Models\User $record): string => "Restablecer contraseña: {$record->name}")
                    ->modalDescription('Asigne una nueva contraseña al colaborador en caso de extravío.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('new_password')
                            ->label('Nueva Contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(\Illuminate\Validation\Rules\Password::default())
                            ->helperText('Mínimo 8 caracteres, debe incluir letras y números.'),
                    ])
                    ->action(function (\App\Models\User $record, array $data): void {
                        $record->update([
                            'password' => \Illuminate\Support\Facades\Hash::make($data['new_password']),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Contraseña actualizada')
                            ->body("La contraseña de {$record->name} ha sido restablecida exitosamente.")
                            ->success()
                            ->send();
                    }),
                Action::make('activities')
                    ->label('Historial')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->color('info')
                    ->url(fn($record) => UserResource::getUrl('activities', ['record' => $record])),
            ]);
    }
}
