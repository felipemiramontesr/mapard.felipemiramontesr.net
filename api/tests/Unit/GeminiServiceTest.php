<?php

use PHPUnit\Framework\TestCase;
use MapaRD\Services\GeminiService;

class GeminiServiceTest extends TestCase
{
    public function testAnalyzeBreachSplitsDataIntoBatches()
    {
        // 1. Arrange: Create 7 dummy breaches (Batch size is 5, so expect 2 batches)
        $breaches = array_fill(0, 7, [
            'name' => 'Breach Test',
            'description' => 'Test Desc',
            'classes' => ['Email', 'Password']
        ]);

        // Mock the Service to intercept 'callGemini' (protected method)
        // Since we can't easily mock protected methods in simple PHPUnit without reflection or partial mocks on the class itself,
        // we will test the public interface behavior.

        // However, `callGemini` does the actual API call. 
        // For this test, we might need a Refactor to inject a "HttpClient" or mock the method.
        // Let's create a partial mock of GeminiService.

        $service = $this->getMockBuilder(GeminiService::class)
            ->setConstructorArgs(['fake_api_key'])
            ->onlyMethods(['callGemini'])
            ->getMock();

        // 2. Expectation: callGemini should be called 3 times:
        //    - Batch 1 (5 items)
        //    - Batch 2 (2 items)
        //    - Summary (1 call)
        $service->expects($this->exactly(3))
            ->method('callGemini')
            ->willReturnCallback(function ($url, $sys, $user) {
                // Return a fake valid JSON response structure
                return [
                    'detailed_analysis' => [
                        ['source_name' => 'Mock Breach', 'incident_story' => 'Story']
                    ],
                    'threat_level' => 'HIGH',
                    'executive_summary' => 'Summary'
                ];
            });

        // 3. Act
        $result = $service->analyzeBreach($breaches);

        // 4. Assert
        $this->assertArrayHasKey('detailed_analysis', $result);
        $this->assertArrayHasKey('threat_level', $result);
        // Note: The count of detailed_analysis depends on our mock return. 
        // Since we return 1 item per call, and called it 2 times for batches, we get 2 items.
        // Real logic would merge 5+2 = 7. 
        // To test exact merging, we'd need more complex callback logic.
        $this->assertCount(2, $result['detailed_analysis']);
    }
}
