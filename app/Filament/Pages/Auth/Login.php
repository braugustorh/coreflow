<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Usuario o Correo Electrónico')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $loginInput = trim((string) ($data['email'] ?? ''));
        $loginField = str_contains($loginInput, '@') ? 'email' : 'user';

        return [
            $loginField => $loginInput,
            'password' => $data['password'],
        ];
    }
}
