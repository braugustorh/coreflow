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
            [
                'code' => 'LJ',
                'name' => 'Luis Perales Juarez',
                'email' => 'Luis.Juarez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.LU/8305',
                'user' => 'Luis.Juarez',
                'is_active' => true,
            ],
            [
                'code' => 'DO',
                'name' => 'Dulce Olivares',
                'email' => 'dulce.olivares@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.DU/1774',
                'user' => 'dulce.olivares',
                'is_active' => true,
            ],
            [
                'code' => 'EC',
                'name' => 'Eric Frias',
                'email' => 'Eric.Frias@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.ER/8898',
                'user' => 'Eric.Frias',
                'is_active' => true,
            ],
            [
                'code' => 'YP',
                'name' => 'Yadir Padilla',
                'email' => 'Yadir.Padilla@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.YA/1336',
                'user' => 'Yadir.Padilla',
                'is_active' => true,
            ],
            [
                'code' => 'VB',
                'name' => 'Victor Bahena',
                'email' => 'VB@coreflow.com',
                'role' => 'Geologo',
                'password' => 'MLE.VI/6421',
                'user' => 'Victor.bahena',
                'is_active' => true,
            ],
            [
                'code' => 'EG',
                'name' => 'Erik Gonzalez',
                'email' => 'Erik.Gonzalez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.ER/1917',
                'user' => 'Erik.Gonzalez',
                'is_active' => true,
            ],
            [
                'code' => 'MF',
                'name' => 'Marilyn Figueroa',
                'email' => 'Marilyn.Figueroa@torexgold.com',
                'role' => 'Supervisor Coreshack',
                'password' => 'MLE.MA/3397',
                'user' => 'Marilyn.Figueroa',
                'is_active' => true,
            ],
            [
                'code' => 'EN',
                'name' => 'Eldai Najera',
                'email' => 'Eldai.Najera@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.EL/2060',
                'user' => 'Eldai.Najera',
                'is_active' => true,
            ],
            [
                'code' => 'AO',
                'name' => 'Adilene Ochoa',
                'email' => 'dulce.ochoa@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.AD/1300',
                'user' => 'dulce.ochoa',
                'is_active' => true,
            ],
            [
                'code' => 'JA',
                'name' => 'Josue Arreola',
                'email' => 'josue.arreola@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.JO/3779',
                'user' => 'josue.arreola',
                'is_active' => true,
            ],
            [
                'code' => 'NC',
                'name' => 'Nayeli Cruz',
                'email' => 'nayely.cruz@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.NA/6484',
                'user' => 'nayely.cruz',
                'is_active' => true,
            ],
            [
                'code' => 'LS',
                'name' => 'Lorena Samperio',
                'email' => 'lorena.samperio@torexgold.com',
                'role' => 'Admin CoreFlow',
                'password' => 'MLE.LO/5564',
                'user' => 'lorena.samperio',
                'is_active' => true,
            ],
            [
                'code' => 'EA',
                'name' => 'Emeri Castillo',
                'email' => 'emeri.castillo@torexgold.com',
                'role' => 'Supervisor Coreshack',
                'password' => 'MLE.EM/4882',
                'user' => 'emeri.castillo',
                'is_active' => true,
            ],
            [
                'code' => 'RGR',
                'name' => 'Raul Guerra',
                'email' => 'raul.guerra@torexgold.com',
                'role' => 'Visor',
                'password' => 'MLE.RA/5998',
                'user' => 'raul.guerra',
                'is_active' => true,
            ],
            [
                'code' => 'JAS',
                'name' => 'Jose Antonio San Vicente',
                'email' => 'jose.sanvicente@torexgold.com',
                'role' => 'Visor',
                'password' => 'MLE.JO/3997',
                'user' => 'jose.sanvicente',
                'is_active' => true,
            ],
            [
                'code' => 'KG',
                'name' => 'Kathia Garcia',
                'email' => 'kathia.garcia@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.KA/4923',
                'user' => 'kathia.garcia',
                'is_active' => true,
            ],
            [
                'code' => 'JM',
                'name' => 'Jose Manuel',
                'email' => 'jose.valdez.r@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.JM/1875',
                'user' => 'jose.valdez.r',
                'is_active' => true,
            ],
            [
                'code' => 'PV',
                'name' => 'Pablo Vasquez',
                'email' => 'Jose.Vasquez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.PV/5381',
                'user' => 'Jose.Vasquez',
                'is_active' => true,
            ],
            [
                'code' => 'JO',
                'name' => 'Julio Ojeda',
                'email' => 'Julio.Ojeda@Torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.JO/4335',
                'user' => 'Julio.Ojeda',
                'is_active' => true,
            ],
            [
                'code' => 'GG',
                'name' => 'Gabriel Gomez',
                'email' => 'GG@coreflow.com',
                'role' => 'Geologo',
                'password' => 'MLE.GG/2843',
                'user' => 'Gabriel.gomez',
                'is_active' => true,
            ],
            [
                'code' => 'DA',
                'name' => 'Diana Ortiz',
                'email' => 'diana.ortiz@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.DA/8732',
                'user' => 'diana.ortiz',
                'is_active' => true,
            ],
            [
                'code' => 'CB',
                'name' => 'Cristo Bejarano',
                'email' => 'cristo.bejarano@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.GA/8133',
                'user' => 'cristo.bejarano',
                'is_active' => true,
            ],
            [
                'code' => 'CN',
                'name' => 'Cristian Nuñez',
                'email' => 'Cristian.Nunez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.CN/9875',
                'user' => 'Cristian.Nunez',
                'is_active' => true,
            ],
            [
                'code' => 'DV',
                'name' => 'David Victoria',
                'email' => 'David.Victoria@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.DV/1825',
                'user' => 'David.Victoria',
                'is_active' => true,
            ],
            [
                'code' => 'RM',
                'name' => 'Raul Martinez',
                'email' => 'Raul.Martinez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.RM/0025',
                'user' => 'Raul.Martinez',
                'is_active' => true,
            ],
            [
                'code' => 'YS',
                'name' => 'Yezmar Suarez',
                'email' => 'dulce.suarez@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.YS/1001',
                'user' => 'dulce.suarez',
                'is_active' => true,
            ],
            [
                'code' => 'LM',
                'name' => 'Leonardo Maganda',
                'email' => 'Leonardo.Maganda@torexgold.com',
                'role' => 'Geologo',
                'password' => 'MLE.LM/9875',
                'user' => 'Leonardo.Maganda',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            $roleName = match ($userData['role']) {
                'Geólogo', 'Geologo' => 'Geologo',
                'Visor (Solo lectura)', 'Visor' => 'Visor',
                default => $userData['role'],
            };

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $user = User::updateOrCreate(
                ['code' => $userData['code']],
                [
                    'user' => $userData['user'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'sede_id' => $sedeId,
                    'is_active' => $userData['is_active'] ?? true,
                ]
            );

            $user->syncRoles([$role->name]);
        }
    }
}
