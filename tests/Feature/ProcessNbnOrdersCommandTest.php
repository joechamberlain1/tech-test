<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnApplication;
use App\Models\Application;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessNbnOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_only_processes_nbn_applications_with_order_status()
    {
        Queue::fake();

        // Create NBN application with 'order' status - should be processed
        $nbnPlan = Plan::factory()->create(['type' => 'nbn']);
        $nbnOrderApp = Application::factory()->create([
            'status' => ApplicationStatus::Order,
            'plan_id' => $nbnPlan->id,
        ]);

        // Create NBN application with 'complete' status - should NOT be processed
        $nbnPlan2 = Plan::factory()->create(['type' => 'nbn']);
        $nbnCompleteApp = Application::factory()->create([
            'status' => ApplicationStatus::Complete,
            'plan_id' => $nbnPlan2->id,
        ]);

        // Create mobile application with 'order' status - should NOT be processed
        $mobilePlan = Plan::factory()->create(['type' => 'mobile']);
        $mobileOrderApp = Application::factory()->create([
            'status' => ApplicationStatus::Order,
            'plan_id' => $mobilePlan->id,
        ]);

        // Create opticomm application with 'order' status - should NOT be processed
        $opticommPlan = Plan::factory()->create(['type' => 'opticomm']);
        $opticommOrderApp = Application::factory()->create([
            'status' => ApplicationStatus::Order,
            'plan_id' => $opticommPlan->id,
        ]);

        $this->artisan('app:process-nbn-orders')->assertSuccessful();

        // Assert only the NBN application with 'order' status was dispatched
        Queue::assertPushed(ProcessNbnApplication::class, 1);
        Queue::assertPushed(ProcessNbnApplication::class, function ($job) use ($nbnOrderApp) {
            return $job->application->id === $nbnOrderApp->id;
        });
    }

    /** @test */
    public function it_dispatches_multiple_nbn_applications_to_queue()
    {
        Queue::fake();

        // Create multiple NBN applications with 'order' status - each with unique plan
        for ($i = 0; $i < 3; $i++) {
            $plan = Plan::factory()->create(['type' => 'nbn']);
            Application::factory()->create([
                'status' => ApplicationStatus::Order,
                'plan_id' => $plan->id,
            ]);
        }

        $this->artisan('app:process-nbn-orders')->assertSuccessful();

        // Assert all three applications were dispatched
        Queue::assertPushed(ProcessNbnApplication::class, 3);
    }
}
