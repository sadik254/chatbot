<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.secret');
        $mode = config('services.paypal.mode');

        // Set the base URL based on the environment (sandbox or live)
        $this->baseUrl = ($mode === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get an OAuth2.0 access token from PayPal.
     * This token is required for all subsequent API calls.
     *
     * @return string The access token
     * @throws \Exception If token generation fails
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken; // Return cached token if available
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm() // Send as application/x-www-form-urlencoded
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                // Optionally, implement token caching with expiration for performance
                return $this->accessToken;
            } else {
                Log::error('PayPal Access Token Generation Failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new \Exception('Failed to get PayPal access token: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Exception during PayPal Access Token Generation', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Creates a product on PayPal using direct API call.
     *
     * @param string $name
     * @param string $description
     * @return object PayPal product response object
     * @throws \Exception
     */
    public function createProduct($name, $description = 'Chatbot Subscription'): object
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => uniqid(), // Recommended for idempotency
            ])->post("{$this->baseUrl}/v1/catalogs/products", [
                'name' => $name,
                'description' => $description,
                'type' => 'SERVICE', // SERVICE or DIGITAL_GOODS
                'category' => 'SOFTWARE', // Choose an appropriate category
                // 'image_url' => 'https://example.com/product-image.jpg', // Optional
                // 'home_url' => 'https://example.com', // Optional
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info('PayPal Product Created Successfully', ['product_id' => $data['id'] ?? 'N/A']);
                return (object) $data; // Return as an object for consistent access
            } else {
                Log::error('PayPal createProduct failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new \Exception('Failed to create PayPal product: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Exception during PayPal createProduct', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Creates a plan on PayPal using direct API call.
     *
     * @param string $productId The PayPal Product ID
     * @param string $price The price as a string (e.g., "10.00")
     * @param string $currency The currency code (e.g., "USD")
     * @param string $interval The billing interval unit (e.g., "MONTH")
     * @param int $intervalCount The number of interval units (e.g., 1 for every month)
     * @return object PayPal plan response object
     * @throws \Exception
     */
    public function createPlan($productId, $price, $currency = 'USD', $interval = 'MONTH', $intervalCount = 1): object
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => uniqid(), // Recommended for idempotency
            ])->post("{$this->baseUrl}/v1/billing/plans", [
                'product_id' => $productId,
                'name' => "Recurring Plan for " . $productId, // You might want a more dynamic name
                'description' => "Subscription plan for " . $productId,
                'status' => 'ACTIVE', // Plans should be active to create subscriptions
                'billing_cycles' => [
                    [
                        "frequency" => [
                            "interval_unit" => $interval,
                            "interval_count" => $intervalCount
                        ],
                        "tenure_type" => "REGULAR",
                        "sequence" => 1,
                        "total_cycles" => 0, // 0 means indefinite billing cycles
                        "pricing_scheme" => [
                            "fixed_price" => [
                                "value" => (string) $price, // Ensure price is a string
                                "currency_code" => $currency
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [
                    "auto_bill_outstanding" => true,
                    "setup_fee_failure_action" => "CONTINUE",
                    "payment_failure_threshold" => 1
                ],
                // 'taxes' => ['percentage' => '10', 'inclusive' => false], // Optional tax details
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info('PayPal Plan Created Successfully', ['plan_id' => $data['id'] ?? 'N/A']);
                return (object) $data; // Return as an object for consistent access
            } else {
                Log::error('PayPal createPlan failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new \Exception('Failed to create PayPal plan: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Exception during PayPal createPlan', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
