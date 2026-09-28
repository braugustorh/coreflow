<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sede;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Sede::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Media Luna',
                'country' => 'MX',
                'state' => 'Guerrero',
                'city' => 'Cocúla',
            ]
        );
    }
}
