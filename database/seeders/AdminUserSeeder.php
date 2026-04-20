<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => ['admin@admin.net']], // Cambia esto
            [
                'name' => 'Administrador',
                'password' => Hash::make('@admin.1984'), // Cambia esto
                'email_verified_at' => now(),
            ]
        );
    }
}
