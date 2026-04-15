<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Tarjeta 1: Información Personal
                Section::make('Información Personal')
                    ->description('Datos básicos y credenciales de acceso del empleado.')
                    ->icon('heroicon-o-user-circle')
                    ->columns(2) // Divide el contenido de esta tarjeta en 2 columnas
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre Completo')
                            ->prefixIcon('heroicon-m-user') // Icono dentro del input
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->prefixIcon('heroicon-m-envelope')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->password()
                            ->revealable() // Agrega el ojito para mostrar/ocultar la contraseña (Gran UX)
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->maxLength(255)
                            ->columnSpanFull(), // Hace que la contraseña ocupe todo el ancho inferior
                    ]),

                // Tarjeta 2: Asignación Operativa y Permisos
                Section::make('Operación y Seguridad')
                    ->description('Define dónde trabaja este usuario y qué nivel de acceso tiene en el sistema.')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        // Asignación de Sede
                        Select::make('sede_id')
                            ->relationship('sede', 'name')
                            ->label('Centro de Trabajo (Sede)')
                            ->prefixIcon('heroicon-m-building-office')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        // Asignación de Roles (Filament Shield)
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->label('Nivel de Acceso (Roles)')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
