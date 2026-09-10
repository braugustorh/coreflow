<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Services\GapCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrillHoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_parent_and_child_drillholes_with_relationships(): void
    {
        $sede = Sede::create(['name' => 'Sede Guerrero', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna East', 'code' => 'MLE', 'sede_id' => $sede->id]);

        $parent = DrillHole::create([
            'nombre_barreno'    => 'MLE26-039',
            'target'            => 'MLE',
            'sede_id'           => $sede->id,
            'proyecto_id'       => $proyecto->id,
            'easting'           => 423282.288,
            'northing'          => 1984057.169,
            'elevation'         => 1143.18,
            'dip'               => -75.45,
            'azimuth'           => 46.54,
            'start_depth'       => 0.00,
            'max_depth'         => 400.00,
            'drilling_type'     => 'Diamantina',
        ]);

        $child = DrillHole::create([
            'nombre_barreno'    => 'MLE26-039W1',
            'is_child'          => true,
            'parent_id'         => $parent->id,
            'target'            => 'MLE',
            'sede_id'           => $sede->id,
            'proyecto_id'       => $proyecto->id,
            // Heredados de la madre
            'easting'           => 423282.288,
            'northing'          => 1984057.169,
            'elevation'         => 1143.18,
            'dip'               => -75.45,
            'azimuth'           => 46.54,
            // Inicia en el punto de desvío (ej. 350m) y termina en 600m
            'start_depth'       => 350.00,
            'max_depth'         => 600.00,
            'drilling_type'     => 'Diamantina',
        ]);

        $this->assertTrue($child->isChild());
        $this->assertFalse($parent->isChild());
        $this->assertTrue($parent->hasChildren());
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertEquals('MLE26-039', $child->parent->nombre_barreno);
        $this->assertCount(1, $parent->children);
        $this->assertEquals('MLE26-039W1', $parent->children->first()->nombre_barreno);
    }

    public function test_gap_calculation_on_child_drillhole_respects_start_depth(): void
    {
        $sede = Sede::create(['name' => 'Sede Guerrero', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna East', 'code' => 'MLE', 'sede_id' => $sede->id]);
        $user = User::factory()->create();

        $child = DrillHole::create([
            'nombre_barreno' => 'MLE26-039W1',
            'is_child'       => true,
            'start_depth'    => 350.00,
            'max_depth'      => 450.00,
            'sede_id'        => $sede->id,
            'proyecto_id'    => $proyecto->id,
        ]);

        // Sin muestras: el gap completo debe ser de 350m a 450m (100m)
        $gaps = GapCalculatorService::calculateGaps($child);
        $this->assertCount(1, $gaps);
        $this->assertEquals(350.00, $gaps[0]['from_depth']);
        $this->assertEquals(450.00, $gaps[0]['to_depth']);

        // Agregar una muestra de 350m a 400m
        DrillHoleSample::create([
            'user_id'       => $user->id,
            'barreno_id'    => $child->id,
            'proyecto_id'   => $proyecto->id,
            'sample_number' => 'SMP-CHILD-01',
            'sample_type'   => 'O',
            'from_depth'    => 350.00,
            'to_depth'      => 400.00,
        ]);

        // Ahora debe quedar un único gap de 400m a 450m
        $gapsAfterSample = GapCalculatorService::calculateGaps($child);
        $this->assertCount(1, $gapsAfterSample);
        $this->assertEquals(400.00, $gapsAfterSample[0]['from_depth']);
        $this->assertEquals(450.00, $gapsAfterSample[0]['to_depth']);
    }
}
