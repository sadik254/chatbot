<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PlanController extends Controller
{
    /**
     * Get all active plans for display.
     */
    public function index()
    {
        $plans = Plan::where('is_active', true)
                     ->orderBy('price', 'asc')
                     ->get();

        return response()->json([
            'data' => $plans
        ]);
    }
}
