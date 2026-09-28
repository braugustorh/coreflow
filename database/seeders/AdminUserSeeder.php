<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Sede;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sede = Sede::where('name', 'Media Luna')->first() ?? Sede::find(1);
        $sedeId = $sede ? $sede->id : 1;

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::updateOrCreate(
            ['email' => 'admin@admin.net'],
            [
                'name' => 'Braulio Augusto Reyes Herrera',
                'password' => Hash::make('@admin.1984'),
                'email_verified_at' => now(),
                'sede_id' => $sedeId,
            ]
        );

        $user->syncRoles([$superAdminRole->name]);
    }
}
