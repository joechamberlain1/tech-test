<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use Illuminate\Http\Request;
use App\Models\Application;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        // Logic to list applications
        $applications = Application::with(['customer', 'plan']);

        // If a plan_type filter is provided, filter by it
        if($request->has('plan_type') && $request->plan_type !== null) {
            $applications->whereHas('plan', function($query) use ($request) {
                $query->where('type', $request->plan_type);
            });
        }

        // sort by oldest first
        $applications->oldest();

        // Get paginated results
        $applications = $applications->paginate(15);

        return ApplicationResource::collection($applications);
    }
}
