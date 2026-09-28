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
        $sede = Sede::where('name', 'Media Luna')->first() ?? Sede::find(1);
        $sedeId = $sede ? $sede->id : 1;

        $users = [
            ['code' => 'LJ',  'name' => 'Luis Perales Juarez',       'email' => 'Luis.Juarez@torexgold.com',    'role' => 'Geologo',              'password' => 'MLE.LU/8305'],
            ['code' => 'DO',  'name' => 'Dulce Olivares',             'email' => 'DO@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.DU/1774'],
            ['code' => 'EC',  'name' => 'Eric Frias',                 'email' => 'EC@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.ER/8898'],
            ['code' => 'YP',  'name' => 'Yadir Padilla',              'email' => 'YP@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.YA/1336'],
            ['code' => 'VB',  'name' => 'Victor Bahena',              'email' => 'VB@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.VI/6421'],
            ['code' => 'EG',  'name' => 'Erik Gonzalez',              'email' => 'EG@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.ER/1917'],
            ['code' => 'MF',  'name' => 'Marilyn Figueroa',           'email' => 'MF@coreflow.com',              'role' => 'Supervisor Coreshack', 'password' => 'MLE.MA/3397'],
            ['code' => 'EN',  'name' => 'Eldai Najera',               'email' => 'EN@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.EL/2060'],
            ['code' => 'AO',  'name' => 'Adilene Ochoa',              'email' => 'AO@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.AD/1300'],
            ['code' => 'JA',  'name' => 'Josue Arreola',              'email' => 'JA@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.JO/3779'],
            ['code' => 'DA',  'name' => 'Diana Almendarez',           'email' => 'DA@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.DI/5087'],
            ['code' => 'GA',  'name' => 'Gabriel Anselmo',            'email' => 'GA@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.GA/8133'],
            ['code' => 'NC',  'name' => 'Nayeli Cruz',                'email' => 'NC@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.NA/6484'],
            ['code' => 'LS',  'name' => 'Lorena Samperio',            'email' => 'lorena.samperio@torexgold.com','role' => 'Admin CoreFlow',        'password' => 'MLE.LO/5564'],
            ['code' => 'EA',  'name' => 'Emeri Avila',                'email' => 'EA@coreflow.com',              'role' => 'Supervisor Coreshack', 'password' => 'MLE.EM/4882'],
            ['code' => 'RGR', 'name' => 'Raul Guerra',                'email' => 'RGR@coreflow.com',             'role' => 'Visor',                'password' => 'MLE.RA/5998'],
            ['code' => 'JAS', 'name' => 'Jose Antonio San Vicente',   'email' => 'JAS@coreflow.com',             'role' => 'Visor',                'password' => 'MLE.JO/3997'],
            ['code' => 'KG',  'name' => 'Kathia Garcia',              'email' => 'KG@coreflow.com',              'role' => 'Geologo',              'password' => 'MLE.KA/4923'],
        ];

        foreach ($users as $userData) {
            $role = Role::firstOrCreate(['name' => $userData['role'], 'guard_name' => 'web']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'code' => $userData['code'],
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'sede_id' => $sedeId,
                ]
            );

            $user->syncRoles([$role->name]);
        }
    }
}
