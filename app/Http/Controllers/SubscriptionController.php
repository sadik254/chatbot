<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use PayPalCheckoutSdk\Subscriptions\SubscriptionsGetRequest;
use PayPalCheckoutSdk\Subscriptions\SubscriptionsCreateRequest;
use App\Services\PayPalService;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    protected $paypal;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypal = $paypalService->client();
    }

    /**
     * Start a PayPal subscription and return approval URL.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        if (!$plan->paypal_plan_id) {
            return response()->json(['error' => 'Plan is not configured with PayPal.'], 422);
        }

        // Create PayPal subscription
        $subscriptionRequest = new SubscriptionsCreateRequest();
        $subscriptionRequest->prefer('return=representation');
        $subscriptionRequest->body = [
            'plan_id' => $plan->paypal_plan_id,
            'application_context' => [
                'brand_name' => 'Pondeet AI Chatbot',
                'return_url' => config('app.frontend_url') . '/subscribe/success',
                'cancel_url' => config('app.frontend_url') . '/subscribe/cancel',
            ]
        ];

        $response = $this->paypal->execute($subscriptionRequest);
        $paypalSubscription = $response->result;

        // Get approval URL
        $approvalUrl = collect($paypalSubscription->links)->firstWhere('rel', 'approve')->href ?? null;

        return response()->json([
            'approval_url' => $approvalUrl,
            'subscription_id' => $paypalSubscription->id
        ]);
    }

    /**
     * Confirm PayPal subscription after frontend approval.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|string'
        ]);

        $paypalSubscriptionId = $request->subscription_id;

        $getRequest = new SubscriptionsGetRequest($paypalSubscriptionId);
        $response = $this->paypal->execute($getRequest);
        $data = $response->result;

        if ($data->status !== 'ACTIVE') {
            return response()->json(['error' => 'Subscription not active.'], 400);
        }

        // Check if already stored
        $existing = Subscription::where('paypal_subscription_id', $paypalSubscriptionId)->first();
        if ($existing) {
            return response()->json(['message' => 'Subscription already confirmed.']);
        }

        $user = Auth::user();
        $plan = Plan::where('paypal_plan_id', $data->plan_id)->first();

        if (!$plan) {
            return response()->json(['error' => 'Plan not found.'], 404);
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'paypal_subscription_id' => $paypalSubscriptionId,
            'status' => 'active',
            'started_at' => now(),
            'next_billing_time' => isset($data->billing_info->next_billing_time)
                ? \Carbon\Carbon::parse($data->billing_info->next_billing_time)
                : null
        ]);

        return response()->json([
            'message' => 'Subscription activated.',
            'subscription' => $subscription
        ]);
    }

    /**
     * Check user's subscription status.
     */
    public function status()
    {
        $user = Auth::user();
        $subscription = $user->subscription;

        if (!$subscription || $subscription->status !== 'active') {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'plan' => $subscription->plan->name,
            'expires_at' => $subscription->next_billing_time
        ]);
    }
}
