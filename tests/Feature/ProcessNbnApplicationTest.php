<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnApplication;
use Tests\TestCase;
use App\Models\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProcessNbnApplicationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_marks_application_as_complete_on_successful_order()
    {
        Http::fake([
            env('NBN_B2B_ENDPOINT') => Http::response(
                json_decode(
                    file_get_contents(
                        base_path('tests/stubs/nbn-successful-response.json')
                    ),
                true
                ), 
                200
            ),
        ]);

        $application = Application::factory()
        ->hasPlan(['type' => 'nbn'])
        ->create([
            'status' => 'order',
        ]);

        (new ProcessNbnApplication($application))->handle();

        $application->refresh();

        $this->assertEquals(ApplicationStatus::Complete, $application->status);
        $this->assertNotNull($application->order_id);
    }

    /** @test */
    public function it_marks_application_as_order_failed_on_failed_order()
    {
        Http::fake([
            env('NBN_B2B_ENDPOINT') => Http::response(
                json_decode(
                    file_get_contents(
                        base_path('tests/stubs/nbn-fail-response.json')
                    ),
                true
                ), 
                200
            ),
        ]);

        $application = Application::factory()
        ->hasPlan(['type' => 'nbn'])
        ->create([
            'status' => 'order',
        ]);

        (new ProcessNbnApplication($application))->handle();

        $application->refresh();

        $this->assertEquals(ApplicationStatus::OrderFailed, $application->status);
        $this->assertNull($application->order_id);
    }

    /** @test */
    public function it_marks_application_as_order_failed_on_http_error()
    {
        Http::fake([
            env('NBN_B2B_ENDPOINT') => Http::response([], 500),
        ]);

        $application = Application::factory()
        ->hasPlan(['type' => 'nbn'])
        ->create([
            'status' => 'order',
        ]);

        (new ProcessNbnApplication($application))->handle();

        $application->refresh();

        $this->assertEquals(ApplicationStatus::OrderFailed, $application->status);
        $this->assertNull($application->order_id);
    }
}
