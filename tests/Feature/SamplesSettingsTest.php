<?php

namespace Tests\Feature;

use App\Models\DrillHoleSample;
use App\Models\SampleSetting;
use App\Services\SampleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SamplesSettingsTest extends TestCase
{
    use RefreshDatabase;
    public function test_sample_settings_singleton_has_default_values(): void
    {
        $settings = SampleSetting::getSettings();

        $this->assertNotNull($settings);
        $this->assertEquals(25.00, $settings->max_sack_weight);
        $this->assertEquals(0.50, $settings->min_sample_weight);
        $this->assertEquals(15.00, $settings->max_sample_weight);
        $this->assertEquals(7.66, $settings->factor_pq);
        $this->assertEquals(4.28, $settings->factor_hq);
        $this->assertEquals(2.40, $settings->factor_nq);
        $this->assertEquals(1.40, $settings->factor_bq);
        $this->assertEquals(3.00, $settings->blank_weight);
        $this->assertEquals(0.065, $settings->standard_weight);
        $this->assertEquals(50.00, $settings->duplicate_ratio);
    }

    public function test_sample_settings_can_update_estimation_factors(): void
    {
        $settings = SampleSetting::getSettings();
        $settings->update([
            'factor_pq'       => 8.10,
            'factor_hq'       => 5.00,
            'factor_nq'       => 2.80,
            'factor_bq'       => 1.60,
            'blank_weight'    => 2.50,
            'standard_weight' => 0.080,
            'duplicate_ratio' => 45.00,
        ]);

        $settings->refresh();
        $this->assertEquals(8.10, $settings->factor_pq);
        $this->assertEquals(5.00, $settings->factor_hq);
        $this->assertEquals(2.80, $settings->factor_nq);
        $this->assertEquals(1.60, $settings->factor_bq);
        $this->assertEquals(2.50, $settings->blank_weight);
        $this->assertEquals(0.080, $settings->standard_weight);
        $this->assertEquals(45.00, $settings->duplicate_ratio);
    }

    public function test_sample_validation_service_uses_dynamic_weight_limits(): void
    {
        // Actualizar configuración a min 1.0 kg y max 10.0 kg
        $settings = SampleSetting::getSettings();
        $settings->update([
            'min_sample_weight' => 1.00,
            'max_sample_weight' => 10.00,
        ]);

        $sede     = \App\Models\Sede::create(['name' => 'San Miguel', 'code' => 'SM', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'San Miguel']);
        $proyecto = \App\Models\Proyecto::create(['nombre' => 'Test Proj', 'code' => 'TP', 'sede_id' => $sede->id]);
        $barreno  = \App\Models\DrillHole::create(['proyecto_id' => $proyecto->id, 'nombre_barreno' => 'DH001']);

        $user     = \App\Models\User::factory()->create();
        $userId   = $user->id;
        $service  = new SampleValidationService();

        // Muestra demasiado ligera (0.3 kg)
        $sampleTooLight = DrillHoleSample::create([
            'proyecto_id'    => $proyecto->id,
            'barreno_id'     => $barreno->id,
            'user_id'        => $userId,
            'status'         => 'draft',
            'capture_source' => 'import',
            'sample_type'    => 'Original',
            'sample_number'  => 'TEST001',
            'from_depth'     => 0,
            'to_depth'       => 1,
            'sample_length'  => 1,
            'length'         => 1,
            'weight'         => 0.3,
        ]);

        $service->validateDraftsForUser($userId);

        $sampleTooLight->refresh();
        $this->assertNotNull($sampleTooLight->errors);
        $this->assertStringContainsString('inferior al mínimo permitido (1 kg)', implode(' ', $sampleTooLight->errors));
    }
}
