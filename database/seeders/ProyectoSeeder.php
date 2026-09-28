<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proyecto;
use App\Models\Sede;

class ProyectoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sede = Sede::where('name', 'Media Luna')->first() ?? Sede::find(1);
        $sedeId = $sede ? $sede->id : 1;

        $proyectos = [
            [
                'id' => 1,
                'nombre' => 'Media Luna East',
                'code' => 'MLE',
                'descripcion' => null,
                'sede_id' => $sedeId,
            ],
            [
                'id' => 2,
                'nombre' => 'Media Luna West',
                'code' => 'MLW',
                'descripcion' => null,
                'sede_id' => $sedeId,
            ],
            [
                'id' => 3,
                'nombre' => 'Todos Santos',
                'code' => 'TDS',
                'descripcion' => null,
                'sede_id' => $sedeId,
            ],
            [
                'id' => 4,
                'nombre' => 'Naranjo',
                'code' => 'NJO',
                'descripcion' => null,
                'sede_id' => $sedeId,
            ],
            [
                'id' => 5,
                'nombre' => 'Atzcala',
                'code' => 'ATZ',
                'descripcion' => null,
                'sede_id' => $sedeId,
            ],
        ];

        foreach ($proyectos as $data) {
            Proyecto::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
