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
     | The public docs only cover https://checkout.wompi.co/p/, but the UAT host
     | below is confirmed working and is the one that pairs with stagtest_ keys.
     | A 403 here is not a wrong host: both hosts sit behind the same WAF, and
     | both reject the request when "redirect-url" names a loopback or private
     | address. See "redirect_url" below.
     */
    'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.co.uat.wompi.dev/p/'),

    /*
     | Where Wompi returns the customer after payment. Defaults to the
     | checkout.result route on whichever host the request came in on.
     |
     | Wompi's WAF rejects loopback and private-network hosts, so this must
     | resolve to a publicly shaped hostname -- browsing the app at
     | http://localhost:8000 produces a redirect-url that Wompi answers with a
     | CloudFront 403. Point this at a tunnel when Wompi has to reach a machine
     | that is not publicly addressable.
     */
    'redirect_url' => env('WOMPI_REDIRECT_URL'),

    'public_key' => env('WOMPI_PUBLIC_KEY'),

    'private_key' => env('WOMPI_PRIVATE_KEY'),

    'events_secret' => env('WOMPI_EVENTS_SECRET'),

    'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),

    /*
     | Wompi Colombia currently settles in Colombian pesos only.
     */
    'currency' => env('WOMPI_CURRENCY', 'COP'),

];
