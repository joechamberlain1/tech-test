<?php

namespace App\Jobs;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Enums\ApplicationStatus;

class ProcessNbnApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(
        public Application $application
    ) {}

    public function handle(): void
    {
        $customer = $this->application->customer;
        $plan     = $this->application->plan;

        $payload = [
            'address_1' => $this->application->address_1,
            'address_2' => $this->application->address_2,
            'city' => $this->application->city,
            'state' => $this->application->state,
            'postcode' => $this->application->postcode,
            'plan_name' => $this->application->plan->name,
        ];

        $response = Http::post(
            env('NBN_B2B_ENDPOINT'),
            $payload
        );

        if ($response->successful() && $response->json('status') === 'Successful' && !empty($response->json('id'))) {
            $this->application->update([
                'order_id' => $response->json('id'),
                'status'   => ApplicationStatus::Complete,
            ]);
        } else {
            $this->application->update([
                'status' => ApplicationStatus::OrderFailed,
            ]);
        }
    }
}
