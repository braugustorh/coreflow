<?php

namespace Tests\Feature;

use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\WorkOrder;
use App\Services\SackFormGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SackFormGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sack_grouping_respects_max_weight_limit(): void
    {
        // Creamos 10 muestras con pesos conocidos (cada una 8.0 kg)
        // Límite de costal = 25.0 kg
        // Costal 1: Muestra 1 (8kg) + Muestra 2 (8kg) + Muestra 3 (8kg) = 24kg (cierra costal)
        // Costal 2: Muestra 4 (8kg) + Muestra 5 (8kg) + Muestra 6 (8kg) = 24kg
        // Costal 3: Muestra 7 (8kg) + Muestra 8 (8kg) + Muestra 9 (8kg) = 24kg
        // Costal 4: Muestra 10 (8kg) = 8kg

        $samples = collect(range(1, 10))->map(function ($i) {
            $s = new DrillHoleSample();
            $s->sample_number = "ML" . str_pad($i, 6, '0', STR_PAD_LEFT);
            $s->weight = 8.0;
            return $s;
        });

        $service = new SackFormGeneratorService();

        $reflection = new \ReflectionClass(SackFormGeneratorService::class);
        $method = $reflection->getMethod('buildSacks');
        $method->setAccessible(true);

        $sacks = $method->invoke($service, $samples, 25.0, 1.0);

        $this->assertCount(4, $sacks);
        $this->assertEquals(1, $sacks[0]['number']);
        $this->assertEquals(24.0, $sacks[0]['weight']);
        $this->assertCount(3, $sacks[0]['samples']);

        $this->assertEquals(4, $sacks[3]['number']);
        $this->assertEquals(8.0, $sacks[3]['weight']);
        $this->assertCount(1, $sacks[3]['samples']);
    }

    public function test_sack_grouping_matches_user_example(): void
    {
        // Ejemplo del usuario: Costal acumula 22.17 kg. La siguiente muestra pesa 4.96 kg (total sería 27.13 kg > 25.0 kg).
        // Debe cerrar el Costal 1 (22.17 kg) y pasar la muestra de 4.96 kg al Costal 2.
        $samplesData = [
            ['sample_number' => 'ML157100', 'weight' => 6.53],
            ['sample_number' => 'ML157101', 'weight' => 3.93],
            ['sample_number' => 'ML157988', 'weight' => 4.00],
            ['sample_number' => 'ML157989', 'weight' => 3.96],
            ['sample_number' => 'ML157990', 'weight' => 3.75], // Acumulado = 22.17 kg
            ['sample_number' => 'ML157993', 'weight' => 4.96], // Excedería 25.0kg (27.13kg) -> Va al Costal 2
            ['sample_number' => 'ML157994', 'weight' => 2.54],
        ];

        $samples = collect($samplesData)->map(function ($d) {
            $s = new DrillHoleSample();
            $s->sample_number = $d['sample_number'];
            $s->weight = $d['weight'];
            return $s;
        });

        $service = new SackFormGeneratorService();

        $reflection = new \ReflectionClass(SackFormGeneratorService::class);
        $method = $reflection->getMethod('buildSacks');
        $method->setAccessible(true);

        $sacks = $method->invoke($service, $samples, 25.0, 1.0);

        $this->assertCount(2, $sacks);
        $this->assertEquals(['ML157100', 'ML157101', 'ML157988', 'ML157989', 'ML157990'], $sacks[0]['samples']);
        $this->assertEquals(22.17, $sacks[0]['weight']);
        $this->assertEquals(['ML157993', 'ML157994'], $sacks[1]['samples']);
        $this->assertEquals(7.50, $sacks[1]['weight']);
    }
}
