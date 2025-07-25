<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/widget/chat.js', function () {
    return response()->file(public_path('js/chat-widget.js'), [
        'Content-Type' => 'application/javascript',
    ]);
});

// routes/web.php (temporary)
Route::get('/debug/paypal-sdk', function () {
    $client = \PaypalServerSdkLib\PaypalServerSdkClientBuilder::init()
        ->clientCredentialsAuthCredentials(
            \PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder::init(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
        )
        ->environment(config('services.paypal.mode') === 'live'
            ? \PaypalServerSdkLib\Environment::LIVE
            : \PaypalServerSdkLib\Environment::SANDBOX
        )
        ->build();

    // List public methods on the client
    dd(get_class($client), get_class_methods($client));
});
