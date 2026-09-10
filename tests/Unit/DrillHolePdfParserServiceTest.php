<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Services\DrillHolePdfParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrillHolePdfParserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_id_and_attributes_from_pdf_text_correctly(): void
    {
        $sampleText = <<<TEXT
FECHA: 14 DE JULIO 2026
HORA DE INICIO: 07:00 AM
HORA FINAL: 19:00 PM
RESPONSABLE: ING. RIGOBERTO MALDONADO MORENO

ATRIBUTOS PLANEADOS
PLANILLA ID ESTE NORTE ELEVACION DIP AZIMUTH
MLE-04 MLE26-039 423,282.00 1,984,057.00 1143 -75.00° 45°

ATRIBUTOS INSTALADOS
PLANILLA ID ESTE NORTE ELEVACION DIP AZIMUTH
MLE-04 MLE26-039 423,282.288 1,984,057.169 1143.176 -75.45° 46.54°
TEXT;

        $service = new DrillHolePdfParserService();
        $extracted = $service->extractDataFromText($sampleText);

        $this->assertEquals('MLE-04', $extracted['planilla']);
        $this->assertEquals('MLE26-039', $extracted['barreno_id']);
        $this->assertEquals('ING. RIGOBERTO MALDONADO MORENO', $extracted['responsible']);
        $this->assertEquals('2026-07-14', $extracted['survey_date']);

        $this->assertEquals(423282.288, $extracted['installed']['easting']);
        $this->assertEquals(1984057.169, $extracted['installed']['northing']);
        $this->assertEquals(1143.176, $extracted['installed']['elevation']);
        $this->assertEquals(-75.45, $extracted['installed']['dip']);
        $this->assertEquals(46.54, $extracted['installed']['azimuth']);

        $this->assertEquals(423282.00, $extracted['planned']['easting']);
        $this->assertEquals(1984057.00, $extracted['planned']['northing']);
        $this->assertEquals(1143.00, $extracted['planned']['elevation']);
        $this->assertEquals(-75.00, $extracted['planned']['dip']);
        $this->assertEquals(45.00, $extracted['planned']['azimuth']);
    }

    public function test_it_saves_drill_hole_with_extracted_pdf_attributes_successfully(): void
    {
        $targetPlanilla = 'MLE26-039'; // Se usa el ID del barreno para la Planilla/ID Informe

        $drillHole = DrillHole::create([
            'nombre_barreno'      => 'MLE26-039',
            'target'              => 'MLE',
            'planilla'            => $targetPlanilla,
            'survey_responsible'  => 'ING. RIGOBERTO MALDONADO MORENO',
            'survey_date'         => '2026-07-14',

            // Atributos Planeados
            'planned_easting'     => 423282.000,
            'planned_northing'    => 1984057.000,
            'planned_elevation'   => 1143.00,
            'planned_dip'         => -75.00,
            'planned_azimuth'     => 45.00,

            // Atributos Instalados
            'easting'             => 423282.288,
            'northing'            => 1984057.169,
            'elevation'           => 1143.18,
            'dip'                 => -75.45,
            'azimuth'             => 46.54,

            // Especificaciones Técnicas
            'start_depth'         => 0.00,
            'max_depth'           => 800.00,
            'drilling_type'       => 'Diamantina',
        ]);

        $this->assertDatabaseHas('drill_holes', [
            'id'             => $drillHole->id,
            'nombre_barreno' => 'MLE26-039',
            'planilla'       => 'MLE26-039',
            'easting'        => 423282.288,
            'northing'       => 1984057.169,
            'dip'            => -75.45,
            'azimuth'        => 46.54,
        ]);

        $this->assertTrue($drillHole->hasInstalledAttributes());
    }
}
