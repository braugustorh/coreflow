<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StandardSample;

class StandardSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $standards = [
            ['standard_name' => 'OREAS609c',  'standard_type' => 'LABSTD', 'status' => true],
            ['standard_name' => 'OREAS627',   'standard_type' => 'LABSTD', 'status' => true],
            ['standard_name' => 'OREAS629',   'standard_type' => 'LABSTD', 'status' => true],
            ['standard_name' => 'OREAS601d',  'standard_type' => 'LABSTD', 'status' => true],
            ['standard_name' => 'OREAS 602c', 'standard_type' => 'LABSTD', 'status' => true],
            ['standard_name' => 'OREAS908b',  'standard_type' => 'LABSTD', 'status' => true],
        ];

        foreach ($standards as $standard) {
            StandardSample::updateOrCreate(
                ['standard_name' => $standard['standard_name']],
                $standard
            );
        }
    }
}
