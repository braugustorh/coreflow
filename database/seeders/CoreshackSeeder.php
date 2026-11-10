<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sede;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Hash;

class CoreshackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Obtener o asignar la Sede por defecto (Media Luna)
        $sede = Sede::where('name', 'Media Luna')->first() ?? Sede::find(1);
        $sedeId = $sede ? $sede->id : 1;

        // 3. Crear o asegurar el Rol Coreshack
        $role = Role::firstOrCreate([
            'name' => 'Coreshack',
            'guard_name' => 'web',
        ]);

        // 4. Permisos estrictos autorizados para Coreshack (Solo crear, ver y editar; no eliminar)
        $permissions = [
            'View:AgileCapture',
            'View:QcPhotoEvidence',
            'ViewAny:DrillHoleSample',
            'View:DrillHoleSample',
            'Create:DrillHoleSample',
            'Update:DrillHoleSample',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // Sincronizar permisos del rol
        $role->syncPermissions($permissions);

        // 5. Lista de los 20 usuarios muestreros
        $users = [
            [
                'name' => 'TELLEZ RODRIGUEZ ALFREDO',
                'code' => 'AT',
                'password' => '1010634.A',
                'user' => 'alfredo.tellez',
            ],
            [
                'name' => 'BARRAGAN LOPEZ HECTOR',
                'code' => 'HB',
                'password' => '1001701.H',
                'user' => 'hector.barragan',
            ],
            [
                'name' => 'DE LA ROSA VARGAS JULIO CESAR',
                'code' => 'CR',
                'password' => '1010210.J',
                'user' => 'julio.delarosa',
            ],
            [
                'name' => 'ASTUDILLO CASTREJON LEONEL',
                'code' => 'LA',
                'password' => '1000254.L',
                'user' => 'leonel.astudillo',
            ],
            [
                'name' => 'VAZQUEZ NAVA HUMBERTO',
                'code' => 'HV',
                'password' => '1010594.H',
                'user' => 'humberto.vazquez',
            ],
            [
                'name' => 'ASTUDILLO SANTIBAÑEZ JOSE RICARDO',
                'code' => 'RA',
                'password' => '1001687.R',
                'user' => 'ricardo.astudillo',
            ],
            [
                'name' => 'TOLENTINO MARTINEZ RONALDO',
                'code' => 'RT',
                'password' => '1001764.R',
                'user' => 'ronaldo.tolentino',
            ],
            [
                'name' => 'GUADARRAMA ORTIZ JOSÉ VENTURA',
                'code' => 'JG',
                'password' => '1000236.V',
                'user' => 'ventura.guadarrama',
            ],
            [
                'name' => 'VAZQUEZ HIDALGO FRANCISCO RAFAEL',
                'code' => 'FV',
                'password' => '1010569.F',
                'user' => 'rafael.vazquez',
            ],
            [
                'name' => 'LOPEZ AGUIRRE PROTACIO',
                'code' => 'PL',
                'password' => '1010477.P',
                'user' => 'protacio.lopez',
            ],
            [
                'name' => 'TELLEZ CASTREJON RAYMUNDO',
                'code' => 'RC',
                'password' => '1000248.R',
                'user' => 'raymundo.tellez',
            ],
            [
                'name' => 'REYES TORRES PROSPERO',
                'code' => 'PR',
                'password' => '1010792.P',
                'user' => 'prospero.reyes',
            ],
            [
                'name' => 'CASTREJON MARTINEZ JORGE',
                'code' => 'JC',
                'password' => '1001546.J',
                'user' => 'jorge.castrejon',
            ],
            [
                'name' => 'VELAZQUEZ BAUTISTA JOSE ANDRES',
                'code' => 'JV',
                'password' => '1000231.J',
                'user' => 'andres.velazquez',
            ],
            [
                'name' => 'MARTINEZ ESCOBAR FERMIN',
                'code' => 'FE',
                'password' => '1000226.F',
                'user' => 'fermin.martinez',
            ],
            [
                'name' => 'BARRAGAN LOPEZ EDGAR EMMANUEL',
                'code' => 'EB',
                'password' => '1001530.E',
                'user' => 'emmanuel.barragan',
            ],
            [
                'name' => 'CASARRUBIAS MARRON ALEXIS IVAN',
                'code' => 'AI',
                'password' => '1010403.A',
                'user' => 'ivan.casarrubias',
            ],
            [
                'name' => 'CARRERA VELAZQUEZ VICTORINO',
                'code' => 'VC',
                'password' => '1010295.V',
                'user' => 'victorino.carrera',
            ],
            [
                'name' => 'ASTUDILLO BONAL ALEX FERNANDO',
                'code' => 'AA',
                'password' => '1010206.A',
                'user' => 'alex.astudillo',
            ],
            [
                'name' => 'PADILLA OCHOA MARIO ANTONIO',
                'code' => 'MP',
                'password' => '1002006.M',
                'user' => 'mario.padilla',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['code' => $userData['code']],
                [
                    'name' => trim($userData['name']),
                    'user' => trim($userData['user']),
                    'email' => strtolower(trim($userData['user'])) . '@torexgold.com',
                    'password' => Hash::make($userData['password']),
                    'sede_id' => $sedeId,
                    'is_active' => true,
                ]
            );

            // Sincronizar exclusivamente el rol Coreshack
            $user->syncRoles([$role->name]);
        }

        // Limpiar caché de permisos al finalizar
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (isset($this->command)) {
            $this->command->info('Rol Coreshack y sus 20 usuarios fueron creados/actualizados con éxito.');
        }
    }
}
