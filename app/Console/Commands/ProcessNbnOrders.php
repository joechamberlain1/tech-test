<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use App\Jobs\ProcessNbnApplication;

class ProcessNbnOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-nbn-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $applications = Application::query()
            ->where('status', 'order')
            ->whereHas('plan', function ($query) {
                $query->where('type', 'nbn');
            })
            ->get();

        foreach ($applications as $application) {
            ProcessNbnApplication::dispatch($application);        }
    }
}
