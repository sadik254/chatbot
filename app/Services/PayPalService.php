<?php

namespace App\Services;

use PayPal\PayPalClient;
use PayPal\Checkout\Orders\OrdersCreateRequest;
use PayPal\Checkout\Orders\OrdersCaptureRequest;

class PayPalService
{
    protected $client;

    public function __construct()
    {
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.secret');
        $mode = config('services.paypal.mode', 'sandbox');

        $this->client = PayPalClient::client($clientId, $clientSecret, $mode);
    }

    public function createOrder($amount, $currency = 'USD')
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount
                ]
            ]],
            'application_context' => [
                'return_url' => route('paypal.success'),
                'cancel_url' => route('paypal.cancel'),
            ]
        ]);

        return $this->client->execute($request);
    }

    public function captureOrder($orderId)
    {
        $request = new OrdersCaptureRequest($orderId);
        $request->prefer('return=representation');
        return $this->client->execute($request);
    }
}
