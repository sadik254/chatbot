<?php

namespace App\Services;

use PayPal\Core\PayPalEnvironment;
use PayPal\Core\PayPalHttpClient;

class PayPalClient
{
    public static function client($clientId, $clientSecret, $mode = 'sandbox')
    {
        $environment = $mode === 'live'
            ? new PayPalEnvironment\LiveEnvironment($clientId, $clientSecret)
            : new PayPalEnvironment\SandboxEnvironment($clientId, $clientSecret);

        return new PayPalHttpClient($environment);
    }
}
