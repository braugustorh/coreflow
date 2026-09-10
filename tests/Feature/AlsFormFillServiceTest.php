<?php

namespace Tests\Feature;

use App\Models\DrillHoleSample;
use App\Models\WorkOrder;
use App\Services\AlsFormFillService;
use Tests\TestCase;

class AlsFormFillServiceTest extends TestCase
{
    public function test_range_grouping_ignores_type_and_groups_strictly_by_numeric_consecutiveness(): void
    {
        // Simulamos la lista de muestras del ejemplo del usuario:
        // ML157100, ML157101 (Rango 1: 2 muestras)
        // ML157988, ML157989, ML157990, ML157993, ML157994 (Rangos 2 y 3)

        $sampleCodes = [
            'ML157100',
            'ML157101',
            'ML157988',
            'ML157989',
            'ML157990',
            'ML157993',
            'ML157994',
        ];

        $samples = collect($sampleCodes)->map(function ($code) {
            $s = new DrillHoleSample();
            $s->sample_number = $code;
            $s->sample_type = 'O';
            return $s;
        });

        $service = new AlsFormFillService();

        // Usamos reflexión para probar el método privado buildRangeGroups
        $reflection = new \ReflectionClass(AlsFormFillService::class);
        $method = $reflection->getMethod('buildRangeGroups');
        $method->setAccessible(true);

        $groups = $method->invoke($service, $samples);

        $this->assertCount(3, $groups);

        // Grupo 1: ML157100 - ML157101 (cantidad 2)
        $this->assertEquals('ML157100', $groups[0]['inicio']);
        $this->assertEquals('ML157101', $groups[0]['fin']);
        $this->assertEquals(2, $groups[0]['cantidad']);
        $this->assertEquals('', $groups[0]['tipo']);

        // Grupo 2: ML157988 - ML157990 (cantidad 3)
        $this->assertEquals('ML157988', $groups[1]['inicio']);
        $this->assertEquals('ML157990', $groups[1]['fin']);
        $this->assertEquals(3, $groups[1]['cantidad']);
        $this->assertEquals('', $groups[1]['tipo']);

        // Grupo 3: ML157993 - ML157994 (cantidad 2)
        $this->assertEquals('ML157993', $groups[2]['inicio']);
        $this->assertEquals('ML157994', $groups[2]['fin']);
        $this->assertEquals(2, $groups[2]['cantidad']);
        $this->assertEquals('', $groups[2]['tipo']);

        // Suma total de muestras = 2 + 3 + 2 = 7
        $totalSum = array_sum(array_column($groups, 'cantidad'));
        $this->assertEquals(7, $totalSum);
    }
}
