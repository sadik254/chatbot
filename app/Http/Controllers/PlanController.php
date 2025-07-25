<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse; // Import JsonResponse

class PlanController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    /**
     * Display a listing of the plans (API endpoint).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $plans = Plan::all();
        return response()->json($plans, 200);
    }

    /**
     * Store a newly created plan in storage and on PayPal (API endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'interval' => 'required|in:DAY,WEEK,MONTH,YEAR',
            'interval_count' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
        ]);

        try {
            // 1. Create Product on PayPal
            // It's good practice to have a single product for all your subscription plans
            // or create one per distinct service type. For simplicity, we'll create one
            // if it doesn't exist, or use a default one.
            // In a real application, you might want to store this product ID
            // in your database or a config file after it's created once.
            $productName = 'Chatbot Service'; // A generic name for your service product
            $productDescription = 'Recurring subscription for chatbot services.';

            // You might want to check if a product already exists before creating a new one
            // For now, let's assume we create it or handle potential duplicates gracefully
            $productResponse = $this->paypalService->createProduct($productName, $productDescription);
            // Access the ID directly as the PayPalService now returns a generic object
            $paypalProductId = $productResponse->id; 

            Log::info('PayPal Product Created', ['product_id' => $paypalProductId]);

            // 2. Create Plan on PayPal
            $planResponse = $this->paypalService->createPlan(
                $paypalProductId,
                (string) $request->price, // Ensure price is a string for PayPal API
                $request->currency,
                $request->interval,
                $request->interval_count
            );
            // Access the ID directly as the PayPalService now returns a generic object
            $paypalPlanId = $planResponse->id; 

            Log::info('PayPal Plan Created', ['plan_id' => $paypalPlanId]);

            // 3. Store Plan in your local database
            $plan = Plan::create([
                'name' => $request->name,
                'paypal_plan_id' => $paypalPlanId,
                'price' => $request->price,
                'currency' => $request->currency,
                'features' => $request->features,
                'is_active' => true, // Assuming new plans are active by default
            ]);

            return response()->json([
                'message' => 'Plan created successfully on PayPal and in your database!',
                'plan' => $plan, // Return the created plan data
                'paypal_product_id' => $paypalProductId,
                'paypal_plan_id' => $paypalPlanId,
            ], 201); // 201 Created status

        } catch (\Exception $e) {
            Log::error('Failed to create plan on PayPal or in database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create plan.',
                'error' => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }

    // You would also have methods for show, update, destroy, etc., all returning JSON.
    // Example for show method:
    /**
     * Display the specified plan (API endpoint).
     *
     * @param  \App\Models\Plan  $plan
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan, 200);
    }

    // Example for update method:
    /**
     * Update the specified plan in storage and on PayPal (API endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Plan  $plan
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'sometimes|required|numeric|min:0.01',
            'currency' => 'sometimes|required|string|size:3',
            'interval' => 'sometimes|required|in:DAY,WEEK,MONTH,YEAR',
            'interval_count' => 'sometimes|required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            // Update plan details in your local database
            $plan->update($request->all());

            // Optional: Update the plan on PayPal if relevant fields changed.
            // This would require a `updatePlan` method in your PayPalService
            // and careful consideration of what fields can be updated via PayPal API.
            // For example:
            /*
            if ($request->has(['price', 'interval', 'interval_count'])) {
                $this->paypalService->updatePlan(
                    $plan->paypal_plan_id,
                    (string) $request->input('price', $plan->price),
                    $request->input('currency', $plan->currency),
                    $request->input('interval', $plan->interval),
                    $request->input('interval_count', $plan->interval_count)
                );
            }
            */

            return response()->json([
                'message' => 'Plan updated successfully.',
                'plan' => $plan,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update plan on PayPal or in database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'plan_id' => $plan->id,
            ]);
            return response()->json([
                'message' => 'Failed to update plan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Example for destroy method:
    /**
     * Remove the specified plan from storage and potentially from PayPal (API endpoint).
     *
     * @param  \App\Models\Plan  $plan
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Plan $plan): JsonResponse
    {
        try {
            // Optional: Deactivate or delete the plan on PayPal first.
            // This would require a `deactivatePlan` or `deletePlan` method in your PayPalService.
            // Be very careful with deleting plans on PayPal if there are active subscriptions.
            // It's usually better to "deactivate" a plan rather than delete it.
            // $this->paypalService->deactivatePlan($plan->paypal_plan_id);

            $plan->delete();

            return response()->json(['message' => 'Plan deleted successfully.'], 204); // 204 No Content

        } catch (\Exception $e) {
            Log::error('Failed to delete plan from database or PayPal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'plan_id' => $plan->id,
            ]);
            return response()->json([
                'message' => 'Failed to delete plan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
