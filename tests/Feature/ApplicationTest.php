<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    /**
     * Test to ensure user is logged out and cannot access applications endpoint
     */
    public function test_user_logged_out()
    {
        $response = $this->getJson('/api/applications');

        $response->assertStatus(403);
    }

    /**
     * Test to ensure user can access applications endpoint when logged in
     */
    public function test_user_logged_in()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/applications');

        $response->assertStatus(200);
    }

    /**
     * Test to ensure applications are filtered by plan_type
     */
    public function test_applications_filtered_by_plan_type()
    {
        $user = User::factory()->create();

        $mobilePlan = Plan::factory()->create(['type' => 'mobile']);
        $opticommPlan = Plan::factory()->create(['type' => 'opticomm']);

        $mobileApp = Application::factory()->create(['plan_id' => $mobilePlan->id]);
        $opticommApp = Application::factory()->create(['plan_id' => $opticommPlan->id]);

        $response = $this->actingAs($user)->getJson('/api/applications?plan_type=opticomm');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($opticommApp->id, $ids);
        $this->assertNotContains($mobileApp->id, $ids);
    }

    /**
     * Test to ensure applications are paginated
     */
    public function test_applications_paginated()
    {
        $user = User::factory()->create();
        //create more than 15 applications to test pagination
        Application::factory()->count(20)->create();

        $response = $this->actingAs($user)->getJson('/api/applications');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(15, $responseData);
    }

    /**
     * Test to ensure applications are sorted by oldest first
     */
    public function test_applications_sorted_by_oldest_first()
    {
        $user = User::factory()->create();
        $middleApplication = Application::factory()->create(
            ['created_at' => now()->subDay()]
        );

        sleep(1); // Ensure different timestamps
        $oldestApplication = Application::factory()->create(
            ['created_at' => now()->subDays(2)]
        );

        sleep(1); // Ensure different timestamps
        $youngestApplication = Application::factory()->create(
            ['created_at' => now()]
        );

        $response = $this->actingAs($user)->getJson('/api/applications');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        
        $this->assertEquals($oldestApplication->id, $ids[0]);
        $this->assertEquals($middleApplication->id, $ids[1]);
        $this->assertEquals($youngestApplication->id, $ids[2]);
    }

    /**
     * Test to show monthly cost is in correct format
     */
    public function test_monthly_cost_format()
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->create([
            'monthly_cost' => 2999,
        ]);

        Application::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/applications');
        $response->assertStatus(200);
        $responseData = $response->json('data');

        $this->assertEquals('$29.99', $responseData[0]['plan_monthly_cost']);
    }

    /**
     * Test to ensure order_id is only present when status is complete
     */
    public function test_order_id_present_only_when_status_complete()
    {
        $user = User::factory()->create();
        $completeApplication = Application::factory()->create([
            'status' => ApplicationStatus::Complete,
            'order_id' => 'ORDER123',
        ]);
        $pendingApplication = Application::factory()->create([
            'status' => ApplicationStatus::Order,
            'order_id' => 'ORDER456',
        ]);
        $response = $this->actingAs($user)->getJson('/api/applications');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $completeData = collect($responseData)->firstWhere('id', $completeApplication->id);
        $pendingData = collect($responseData)->firstWhere('id', $pendingApplication->id);

        $this->assertEquals($completeData['order_id'], "ORDER123");
        $this->assertArrayNotHasKey('order_id', $pendingData);
    }

    /**
     * Test to ensure address is formatted correctly
     */    
    public function test_address_format()
    {
        $user = User::factory()->create();

        $customer = Customer::factory()->create([
            'first_name' => 'Homer',
            'last_name' => 'Simpson',
        ]);

        $application = Application::factory()->create([
            'customer_id' => $customer->id,
            'address_1' => 'Apt 4B',
            'address_2' => '742 Evergreen Terrace',
            'city' => 'Melbourne',
            'state' => 'VIC',
            'postcode' => '1111',
        ]);

        $response = $this->actingAs($user)->getJson('/api/applications');
        $response->assertStatus(200);
        $responseData = $response->json('data');

        $applicationData = collect($responseData)->firstWhere('id', $application->id);

        $this->assertEquals('Apt 4B 742 Evergreen Terrace, Melbourne, VIC 1111', $applicationData['address']);
        $this->assertEquals('Homer Simpson', $applicationData['customer_name']);    
    }
}