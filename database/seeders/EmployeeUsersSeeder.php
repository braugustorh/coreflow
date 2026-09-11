<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sede;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class EmployeeUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['code' => 'LJ', 'name' => 'Luis Perales Juarez', 'email' => 'LJ@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'LJ.coreflow-Tmp26'],
            ['code' => 'DO', 'name' => 'Dulce Olivares', 'email' => 'DO@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'DO.coreflow-Tmp26'],
            ['code' => 'EC', 'name' => 'Eric Frias', 'email' => 'EC@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'EC.coreflow-Tmp26'],
            ['code' => 'YP', 'name' => 'Yadir Padilla', 'email' => 'YP@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'YP.coreflow-Tmp26'],
            ['code' => 'VB', 'name' => 'Victor Bahena', 'email' => 'VB@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'VB.coreflow-Tmp26'],
            ['code' => 'EG', 'name' => 'Erik Gonzalez', 'email' => 'EG@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'EG.coreflow-Tmp26'],
            ['code' => 'MF', 'name' => 'Marilyn Figueroa', 'email' => 'MF@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Supervisor Coreshack', 'password' => 'MF.coreflow-Tmp26'],
            ['code' => 'EN', 'name' => 'Eldai Najera', 'email' => 'EN@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'EN.coreflow-Tmp26'],
            ['code' => 'AO', 'name' => 'Adilene Ochoa', 'email' => 'AO@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'AO.coreflow-Tmp26'],
            ['code' => 'JA', 'name' => 'Josue Arreola', 'email' => 'JA@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'JA.coreflow-Tmp26'],
            ['code' => 'DA', 'name' => 'Diana Almendarez', 'email' => 'DA@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'DA.coreflow-Tmp26'],
            ['code' => 'GA', 'name' => 'Gabriel Anselmo', 'email' => 'GA@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'GA.coreflow-Tmp26'],
            ['code' => 'NC', 'name' => 'Nayeli Cruz', 'email' => 'NC@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'NC.coreflow-Tmp26'],
            ['code' => 'LS', 'name' => 'Lorena Samperio', 'email' => 'LS@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Admin', 'password' => 'LS.coreflow-Tmp26'],
            ['code' => 'EA', 'name' => 'Emeri Avila', 'email' => 'EA@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Supervisor Coreshack', 'password' => 'EA.coreflow-Tmp26'],
            ['code' => 'RGR', 'name' => 'Raul Guerra', 'email' => 'RGR@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Visor', 'password' => 'RGR.coreflow-Tmp26'],
            ['code' => 'JAS', 'name' => 'Jose Antonio San Vicente', 'email' => 'JAS@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Visor', 'password' => 'JAS.coreflow-Tmp26'],
            ['code' => 'KG', 'name' => 'Kathia Garcia', 'email' => 'KG@coreflow.com', 'distrito' => 'Media Luna', 'role' => 'Geologo', 'password' => 'KG.coreflow-Tmp26'],
        ];

        foreach ($users as $userData) {
            $sede = Sede::firstOrCreate(['name' => $userData['distrito']]);
            $role = Role::firstOrCreate(['name' => $userData['role'], 'guard_name' => 'web']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'code' => $userData['code'],
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'sede_id' => $sede->id,
                ]
            );

            $user->assignRole($role);
        }
    }
}
