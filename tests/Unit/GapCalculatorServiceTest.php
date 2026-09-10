<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Models\DrillHoleGapValidation;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Services\GapCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private Proyecto $proyecto;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create([
            'name' => 'Distrito Test',
            'country' => 'MX',
            'state' => 'Sonora',
            'city' => 'Hermosillo',
        ]);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Test Project',
            'code' => 'TP01',
            'sede_id' => $this->sede->id,
        ]);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'sede_id' => $this->sede->id,
        ]);
    }

    public function test_it_calculates_full_gap_when_no_samples_or_validations_exist(): void
    {
        $drillHole = DrillHole::create([
            'nombre_barreno' => 'DH-TEST-001',
            'proyecto_id' => $this->proyecto->id,
            'sede_id' => $this->sede->id,
            'max_depth' => 100.0,
        ]);

        $gaps = GapCalculatorService::calculateGaps($drillHole);

        $this->assertCount(1, $gaps);
        $this->assertEquals(0.0, $gaps[0]['from_depth']);
        $this->assertEquals(100.0, $gaps[0]['to_depth']);
    }

    public function test_it_calculates_intermediate_and_end_gaps(): void
    {
        $drillHole = DrillHole::create([
            'nombre_barreno' => 'DH-TEST-002',
            'proyecto_id' => $this->proyecto->id,
            'sede_id' => $this->sede->id,
            'max_depth' => 100.0,
        ]);

        // Sample from 0 to 20
        DrillHoleSample::create([
            'barreno_id' => $drillHole->id,
            'proyecto_id' => $this->proyecto->id,
            'user_id' => $this->user->id,
            'from_depth' => 0.0,
            'to_depth' => 20.0,
        ]);

        // Sample from 50 to 70
        DrillHoleSample::create([
            'barreno_id' => $drillHole->id,
            'proyecto_id' => $this->proyecto->id,
            'user_id' => $this->user->id,
            'from_depth' => 50.0,
            'to_depth' => 70.0,
        ]);

        $gaps = GapCalculatorService::calculateGaps($drillHole);

        $this->assertCount(2, $gaps);
        $this->assertEquals(20.0, $gaps[0]['from_depth']);
        $this->assertEquals(50.0, $gaps[0]['to_depth']);
        $this->assertEquals(70.0, $gaps[1]['from_depth']);
        $this->assertEquals(100.0, $gaps[1]['to_depth']);
    }

    public function test_validations_fill_the_gaps(): void
    {
        $drillHole = DrillHole::create([
            'nombre_barreno' => 'DH-TEST-003',
            'proyecto_id' => $this->proyecto->id,
            'sede_id' => $this->sede->id,
            'max_depth' => 100.0,
        ]);

        DrillHoleSample::create([
            'barreno_id' => $drillHole->id,
            'proyecto_id' => $this->proyecto->id,
            'user_id' => $this->user->id,
            'from_depth' => 0.0,
            'to_depth' => 50.0,
        ]);

        DrillHoleGapValidation::create([
            'barreno_id' => $drillHole->id,
            'from_depth' => 50.0,
            'to_depth' => 100.0,
            'reason' => 'Core Loss',
            'validated_by' => $this->user->id,
            'validated_at' => now(),
        ]);

        $gaps = GapCalculatorService::calculateGaps($drillHole);

        $this->assertEmpty($gaps);
    }

    public function test_get_visual_segments_includes_rich_validation_data(): void
    {
        $drillHole = DrillHole::create([
            'nombre_barreno' => 'DH-TEST-VISUAL',
            'proyecto_id'    => $this->proyecto->id,
            'sede_id'        => $this->sede->id,
            'start_depth'    => 0.0,
            'max_depth'      => 100.0,
        ]);

        DrillHoleSample::create([
            'barreno_id'    => $drillHole->id,
            'proyecto_id'   => $this->proyecto->id,
            'user_id'       => $this->user->id,
            'from_depth'    => 0.0,
            'to_depth'      => 40.0,
            'sample_type'   => 'Original',
        ]);

        DrillHoleGapValidation::create([
            'barreno_id'   => $drillHole->id,
            'from_depth'   => 40.0,
            'to_depth'     => 60.0,
            'reason'       => 'Core Loss',
            'notes'        => 'Zona muy fracturada',
            'validated_by' => $this->user->id,
            'validated_at' => now(),
        ]);

        $segments = GapCalculatorService::getVisualSegments($drillHole);

        $valSegment = collect($segments)->firstWhere('type', 'validation');
        $this->assertNotNull($valSegment);
        $this->assertEquals(40.0, $valSegment['from']);
        $this->assertEquals(60.0, $valSegment['to']);
        $this->assertEquals(20.0, $valSegment['length']);
        $this->assertEquals('Core Loss', $valSegment['reason']);
        $this->assertEquals('Zona muy fracturada', $valSegment['notes']);
        $this->assertEquals('Test User', $valSegment['validated_by']);
        $this->assertNotNull($valSegment['validated_at']);
    }
}
