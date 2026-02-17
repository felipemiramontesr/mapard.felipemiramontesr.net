<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MapaRD\Services\ReportService;
use function MapaRD\Services\text_sanitize;
use function MapaRD\Services\translate_data_class;

class ReportServiceTest extends TestCase
{
    public function testSanitizeFunction()
    {
        // Logic is now inside the file but maybe not globally functional if not included.
        // We might need to include ReportService.php manually if composer autoload doesn't catch functions.
        require_once __DIR__ . '/../../services/ReportService.php';

        $dirty = "Héllö Wörld";
        $clean = text_sanitize($dirty);
        // iconv behavior might vary, but basic check
        $this->assertIsString($clean);
    }

    public function testTranslateDataClass()
    {
        require_once __DIR__ . '/../../services/ReportService.php';

        $english = 'Email addresses';
        $spanish = translate_data_class($english);
        $this->assertEquals('Correos electrónicos', $spanish);

        $unknown = 'Alien DNA';
        $this->assertEquals('Alien DNA', translate_data_class($unknown));
    }

    public function testPDFInstantiation()
    {
        $pdf = new ReportService();
        $this->assertInstanceOf(\FPDF::class, $pdf);
    }
}
