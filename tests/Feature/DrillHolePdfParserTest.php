<?php

namespace Tests\Feature;

use App\Services\DrillHolePdfParserService;
use Tests\TestCase;

class DrillHolePdfParserTest extends TestCase
{
    public function test_extracts_data_from_pdf_text_sample(): void
    {
        $sampleText = <<<TEXT
Arquitecutura-ingeniería-urbanismo
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

NOTA:
LA MÁQUINA INSTALADA CANMEX 712 FUE REVISADA...
TEXT;

        $service = new DrillHolePdfParserService();
        $data = $service->extractDataFromText($sampleText);

        $this->assertEquals('MLE-04', $data['planilla']);
        $this->assertEquals('MLE26-039', $data['barreno_id']);
        $this->assertEquals('ING. RIGOBERTO MALDONADO MORENO', $data['responsible']);
        $this->assertEquals('2026-07-14', $data['survey_date']);

        // Planeados
        $this->assertEquals(423282.00, $data['planned']['easting']);
        $this->assertEquals(1984057.00, $data['planned']['northing']);
        $this->assertEquals(1143.00, $data['planned']['elevation']);
        $this->assertEquals(-75.00, $data['planned']['dip']);
        $this->assertEquals(45.00, $data['planned']['azimuth']);

        // Instalados
        $this->assertEquals(423282.288, $data['installed']['easting']);
        $this->assertEquals(1984057.169, $data['installed']['northing']);
        $this->assertEquals(1143.176, $data['installed']['elevation']);
        $this->assertEquals(-75.45, $data['installed']['dip']);
        $this->assertEquals(46.54, $data['installed']['azimuth']);
    }
}
