<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SampleSetting;

class SampleSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SampleSetting::updateOrCreate(
            ['id' => 1],
            [
                'max_sack_weight' => 25.00,
                'min_sample_weight' => 0.50,
                'max_sample_weight' => 15.00,
                'factor_pq' => 7.66,
                'factor_hq' => 4.28,
                'factor_nq' => 2.40,
                'factor_bq' => 1.40,
                'blank_weight' => 3.00,
                'standard_weight' => 0.065,
                'duplicate_ratio' => 50.00,
            ]
        );
    }
}
