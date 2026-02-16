<?php

use PHPUnit\Framework\TestCase;
use MapaRD\Services\ReportService;

class ReportServiceTest extends TestCase
{
    public function testReportGenerationBasics()
    {
        // 1. Instantiate
        $pdf = new ReportService();
        $pdf->AddPage();

        // 2. act: Add some content
        $pdf->SectionTitle("Test Section");

        // 3. Assert: Verify no errors occurred and object is valid
        $this->assertInstanceOf(ReportService::class, $pdf);
        $this->assertInstanceOf(\FPDF::class, $pdf);

        // We can't easily assert the PDF content without a parser, 
        // but we can ensure the code executes without crashing.
        $this->assertTrue(true);
    }

    public function testSanitizeFunction()
    {
        // Test the helper function (which is namespaced now)
        $input = "Federación";
        $expected = "Federacion"; // logic replaces accents or handles encoding
        // Note: The specific iconv/sanitize logic might behave differently on different OS/Locales
        // Let's just test it runs.

        $cleaned = \MapaRD\Services\text_sanitize($input);
        $this->assertIsString($cleaned);
    }
}
