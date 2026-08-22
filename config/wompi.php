<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wompi Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Credentials are read from the environment and never committed. Only the
    | public key is ever exposed to the browser; the private, events and
    | integrity secrets stay server side.
    |
    | Keys must match their environment: stagtest_ keys belong to the UAT
    | hosts, test_ keys to https://sandbox.wompi.co, prod_ keys to production.
    |
    */

    'base_url' => env('WOMPI_BASE_URL', 'https://api-sandbox.co.uat.wompi.dev/v1'),

    /*
     | The Web Checkout endpoint the pay button redirects to.
     |
     | The public docs only cover https://checkout.wompi.co/p/. The UAT host
     | below mirrors the UAT API host but is unverified, so it is overridable
     | from the environment: if paying lands on a 404, correct it there.
     */
    'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.co.uat.wompi.dev/p/'),

    'public_key' => env('WOMPI_PUBLIC_KEY'),

    'private_key' => env('WOMPI_PRIVATE_KEY'),

    'events_secret' => env('WOMPI_EVENTS_SECRET'),

    'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),

    /*
     | Wompi Colombia currently settles in Colombian pesos only.
     */
    'currency' => env('WOMPI_CURRENCY', 'COP'),

];
