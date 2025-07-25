<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    /**
     * Handles the completion of a PayPal subscription initiated from the frontend.
     * This method receives the PayPal subscription ID and records it in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function completePayPalSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'paypal_subscription_id' => 'required|string',
            'plan_id' => 'required|exists:plans,id', // Your local plan ID
            // 'user_id' => 'required|exists:users,id', // If you're explicitly sending user_id from frontend
        ]);

        // Assuming user is authenticated via API token (e.g., Sanctum)
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $plan = Plan::find($request->plan_id);

        if (!$plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        try {
            // Optional: Fetch full subscription details from PayPal for verification
            // This is highly recommended to ensure the subscription is valid and active.
            $paypalSubscriptionDetails = $this->paypalService->getSubscriptionDetails($request->paypal_subscription_id);

            // Check if the subscription is in an 'ACTIVE' state on PayPal
            if (!isset($paypalSubscriptionDetails->status) || $paypalSubscriptionDetails->status !== 'ACTIVE') {
                Log::warning('PayPal subscription not active', [
                    'paypal_subscription_id' => $request->paypal_subscription_id,
                    'status' => $paypalSubscriptionDetails->status ?? 'N/A',
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'PayPal subscription is not active. Please try again or contact support.',
                    'paypal_status' => $paypalSubscriptionDetails->status ?? 'N/A'
                ], 400);
            }

            // Check if this subscription already exists in your database to prevent duplicates
            $existingSubscription = Subscription::where('paypal_subscription_id', $request->paypal_subscription_id)
                                                ->first();
            if ($existingSubscription) {
                Log::info('Attempted to create duplicate subscription', [
                    'paypal_subscription_id' => $request->paypal_subscription_id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'Subscription already exists.',
                    'subscription' => $existingSubscription
                ], 200); // Return 200 OK as it's not an error
            }

            // Extract relevant dates from PayPal response
            $startedAt = $paypalSubscriptionDetails->start_time ? new \DateTime($paypalSubscriptionDetails->start_time) : now();
            // PayPal's next_billing_time is often in the `billing_info` object
            $nextBillingTime = null;
            if (isset($paypalSubscriptionDetails->billing_info->next_billing_time)) {
                $nextBillingTime = new \DateTime($paypalSubscriptionDetails->billing_info->next_billing_time);
            }


            // Store the subscription in your local database
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'paypal_subscription_id' => $request->paypal_subscription_id,
                'status' => $paypalSubscriptionDetails->status, // Use PayPal's status
                'started_at' => $startedAt,
                'next_billing_time' => $nextBillingTime,
                'cancelled_at' => null, // Initially not cancelled
            ]);

            Log::info('User subscribed successfully', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'paypal_subscription_id' => $request->paypal_subscription_id
            ]);

            return response()->json([
                'message' => 'Subscription completed successfully.',
                'subscription' => $subscription,
            ], 201); // 201 Created

        } catch (\Exception $e) {
            Log::error('Failed to complete PayPal subscription', [
                'error' => $e->getMessage(),
                'paypal_subscription_id' => $request->paypal_subscription_id,
                'user_id' => $user->id ?? 'guest',
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to complete subscription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // You might add other methods here for managing subscriptions (e.g., cancel, view)
    // Example: Fetch user's active subscriptions
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $subscriptions = $user->subscriptions()->with('plan')->get();
        return response()->json($subscriptions, 200);
    }

    // Example: Cancel a subscription
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        // Ensure the user owns this subscription or has permission to cancel
        if (Auth::id() !== $subscription->user_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            // Call PayPal API to cancel the subscription
            // You'd need a `cancelSubscription` method in your PayPalService
            // $this->paypalService->cancelSubscription($subscription->paypal_subscription_id);

            // Update local database status
            $subscription->update([
                'status' => 'CANCELLED', // Or 'INACTIVE', depending on your PayPal status mapping
                'cancelled_at' => now(),
            ]);

            Log::info('Subscription cancelled successfully', [
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->paypal_subscription_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json(['message' => 'Subscription cancelled successfully.'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->paypal_subscription_id,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'message' => 'Failed to cancel subscription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
