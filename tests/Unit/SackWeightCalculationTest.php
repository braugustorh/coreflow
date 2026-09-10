<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\SampleSetting;
use App\Models\Sede;
use App\Models\User;
use App\Services\SackFormGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SackWeightCalculationTest extends TestCase
{
    use RefreshDatabase;

    private SackFormGeneratorService $service;
    private SampleSetting $settings;
    private int $proyectoId;
    private int $barrenoId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SackFormGeneratorService();
        $this->settings = SampleSetting::getSettings();

        $sede = Sede::create(['name' => 'San Miguel', 'code' => 'SM', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'San Miguel']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna', 'code' => 'ML', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['proyecto_id' => $proyecto->id, 'nombre_barreno' => 'TDS25-007', 'core_size' => 'HQ']);
        $user = User::factory()->create();

        $this->proyectoId = $proyecto->id;
        $this->barrenoId = $barreno->id;
        $this->userId = $user->id;
    }

    public function test_resolves_physical_weight_when_available(): void
    {
        $sample = new DrillHoleSample([
            'sample_type'   => 'Original',
            'sample_number' => 'ML1001',
            'weight'        => 5.42,
            'core_size'     => 'HQ',
            'sample_length' => 1.5,
        ]);

        $weight = $this->service->resolveSampleWeight($sample, $this->settings);
        $this->assertEquals(5.42, $weight);
    }

    public function test_resolves_blank_control_sample_weight(): void
    {
        $sample = new DrillHoleSample([
            'sample_type'   => 'Control',
            'control_type'  => 'Blanco',
            'sample_number' => 'ML1002',
            'weight'        => null,
        ]);

        $weight = $this->service->resolveSampleWeight($sample, $this->settings);
        $this->assertEquals(3.00, $weight);
    }

    public function test_resolves_standard_control_sample_weight(): void
    {
        $sample = new DrillHoleSample([
            'sample_type'   => 'Control',
            'control_type'  => 'Estándar',
            'sample_number' => 'ML1003',
            'weight'        => null,
        ]);

        $weight = $this->service->resolveSampleWeight($sample, $this->settings);
        $this->assertEquals(0.065, $weight);
    }

    public function test_resolves_duplicate_sample_weight_from_linked_original(): void
    {
        $original = DrillHoleSample::create([
            'proyecto_id'    => $this->proyectoId,
            'barreno_id'     => $this->barrenoId,
            'user_id'        => $this->userId,
            'sample_type'    => 'Original',
            'sample_number'  => 'ML1004',
            'from_depth'     => 10.0,
            'to_depth'       => 11.5,
            'sample_length'  => 1.5,
            'core_size'      => 'HQ', // 1.5 * 4.28 = 6.42 kg
        ]);

        $duplicate = DrillHoleSample::create([
            'proyecto_id'         => $this->proyectoId,
            'barreno_id'          => $this->barrenoId,
            'user_id'             => $this->userId,
            'sample_type'         => 'Control',
            'control_type'        => 'Duplicado',
            'duplicate_sample_id' => $original->id,
            'sample_number'       => 'ML1005',
        ]);

        // Duplicado debe ser 50% de la original (6.42 * 0.5 = 3.21 kg)
        $weight = $this->service->resolveSampleWeight($duplicate, $this->settings);
        $this->assertEquals(3.21, $weight);
    }

    public function test_resolves_original_sample_weights_by_core_sizes(): void
    {
        // HQ: 1.5m * 4.28 = 6.42 kg
        $sampleHQ = new DrillHoleSample(['sample_type' => 'Original', 'core_size' => 'HQ', 'sample_length' => 1.5]);
        $this->assertEquals(6.42, $this->service->resolveSampleWeight($sampleHQ, $this->settings));

        // NQ: 2.0m * 2.40 = 4.80 kg
        $sampleNQ = new DrillHoleSample(['sample_type' => 'Original', 'core_size' => 'NQ', 'sample_length' => 2.0]);
        $this->assertEquals(4.80, $this->service->resolveSampleWeight($sampleNQ, $this->settings));

        // PQ: 1.0m * 7.66 = 7.66 kg
        $samplePQ = new DrillHoleSample(['sample_type' => 'Original', 'core_size' => 'PQ', 'sample_length' => 1.0]);
        $this->assertEquals(7.66, $this->service->resolveSampleWeight($samplePQ, $this->settings));

        // BQ: 3.0m * 1.40 = 4.20 kg
        $sampleBQ = new DrillHoleSample(['sample_type' => 'Original', 'core_size' => 'BQ', 'sample_length' => 3.0]);
        $this->assertEquals(4.20, $this->service->resolveSampleWeight($sampleBQ, $this->settings));
    }

    public function test_build_sacks_respects_max_sack_weight_using_estimated_weights(): void
    {
        // Creamos 6 muestras HQ de 1.5m cada una (peso estimado: 6.42 kg cada una)
        // Costal 1: Muestra 1 (6.42) + Muestra 2 (6.42) + Muestra 3 (6.42) = 19.26 kg
        // Muestra 4 sumaría 25.68 kg (> 25.0 kg), por lo que debe abrir Costal 2
        // Costal 2: Muestra 4 (6.42) + Muestra 5 (6.42) + Muestra 6 (6.42) = 19.26 kg
        $samples = collect([
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M01', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M02', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M03', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M04', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M05', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M06', 'core_size' => 'HQ', 'sample_length' => 1.5]),
        ]);

        $sacks = $this->service->buildSacks($samples, 25.0, 1.0, $this->settings);

        $this->assertCount(2, $sacks);
        $this->assertEquals(['M01', 'M02', 'M03'], $sacks[0]['samples']);
        $this->assertEquals(19.26, $sacks[0]['weight']);
        $this->assertEquals(['M04', 'M05', 'M06'], $sacks[1]['samples']);
        $this->assertEquals(19.26, $sacks[1]['weight']);
    }

    public function test_build_sacks_includes_blanks_and_standards_correctly(): void
    {
        // M01 (HQ 1.5m => 6.42 kg)
        // M02 (HQ 1.5m => 6.42 kg)
        // BLANK (Blanco => 3.00 kg)
        // M03 (HQ 1.5m => 6.42 kg)
        // Suma parcial = 6.42 + 6.42 + 3.00 + 6.42 = 22.26 kg (entra en costal 1)
        // OREAS (Estándar => 0.065 kg)
        // Suma parcial = 22.26 + 0.065 = 22.325 kg (entra en costal 1)
        // M04 (HQ 1.5m => 6.42 kg) -> 22.325 + 6.42 = 28.745 kg (> 25kg) -> Costal 2
        $samples = collect([
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M01', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M02', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Control', 'control_type' => 'Blanco', 'sample_number' => 'BLK01']),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M03', 'core_size' => 'HQ', 'sample_length' => 1.5]),
            new DrillHoleSample(['sample_type' => 'Control', 'control_type' => 'Estándar', 'sample_number' => 'STD01']),
            new DrillHoleSample(['sample_type' => 'Original', 'sample_number' => 'M04', 'core_size' => 'HQ', 'sample_length' => 1.5]),
        ]);

        $sacks = $this->service->buildSacks($samples, 25.0, 1.0, $this->settings);

        $this->assertCount(2, $sacks);
        $this->assertEquals(['M01', 'M02', 'BLK01', 'M03', 'STD01'], $sacks[0]['samples']);
        $this->assertEquals(22.325, $sacks[0]['weight']);
        $this->assertEquals(['M04'], $sacks[1]['samples']);
        $this->assertEquals(6.42, $sacks[1]['weight']);
    }
}
