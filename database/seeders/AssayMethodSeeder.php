<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssayMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['code' => '3A_AAS', 'name' => '3 Acid Digest (HNO3, HClO4 digestion; HCl leach), unspecified AAS finish.'],
            ['code' => '3A_FAAS', 'name' => '4 Acid Digest (HNO3, HClO4 digestion; HCl leach), flame AAS finish.'],
            ['code' => '3A_GAAS', 'name' => '5 Acid Digest (HNO3, HClO4 digestion; HCl leach), graphite furnace AAS finish.'],
            ['code' => '3A_ICPEMS', 'name' => '3 Acid Digest (HNO3, HClO4 digestion; HCl leach), enhanced ICP-MS finish.'],
            ['code' => '3A_ICPEOES', 'name' => '3 Acid Digest (HNO3, HClO4 digestion; HCl leach), enhanced ICP-OES finish.'],
            ['code' => '3A_ICPMS', 'name' => '3 Acid Digest (HNO3, HClO4 digestion; HCl leach), ICP-MS finish.'],
            ['code' => '3A_ICPOES', 'name' => '3 Acid Digest (HNO3, HClO4 digestion; HCl leach), ICP-OES finish.'],
            ['code' => '3A_SAAS', 'name' => '5 Acid Digest (HNO3, HClO4 digestion; HCl leach), solvent extraction AAS finish.'],
            ['code' => 'FA_AAS', 'name' => 'Fire Assay, unspecified AAS finish.'],
            ['code' => 'FA_FAAS', 'name' => 'Fire Assay, flame AAS finish.'],
            ['code' => 'FA_FAAS2', 'name' => 'Fire Assay / Flame AA'],
            ['code' => 'FA_GAAS', 'name' => 'Fire Assay, graphite furnace AAS finish.'],
            ['code' => 'FA_GRAV', 'name' => 'Fire Assay, gravimetric finish.'],
            ['code' => 'FA_HGA', 'name' => 'Fire Assay, HGA Finish'],
            ['code' => 'FA_ICPAES', 'name' => 'Fire Assay, ICP-AES FINISH'],
            ['code' => 'FA_ICPMS', 'name' => 'Fire Assay. Finish by ICP-MS'],
            ['code' => 'FA_ICPOES', 'name' => 'Fire Assay. Finish by ICP-OES'],
            ['code' => 'FA_ICPUN', 'name' => 'Fire Assay, ICP-unknown finish.'],
            ['code' => 'FA_MSC', 'name' => 'Fire Assay. Finish by Metallic Screen.'],
            ['code' => 'FA_NAA', 'name' => 'Fire Assay. Finish by Neutron Activation Analysis.'],
            ['code' => 'FA_SAAS', 'name' => 'Fire Assay, solvent extraction AAS finish.'],
            ['code' => 'FA_UN', 'name' => 'Fire Assay with unknown finish.'],
        ];

        foreach ($methods as $method) {
            \App\Models\AssayMethod::updateOrCreate(['code' => $method['code']], ['name' => $method['name']]);
        }
    }
}
