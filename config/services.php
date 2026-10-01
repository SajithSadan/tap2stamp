<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // "Continue with Google" for shop owners (Laravel Socialite). A relative
    // redirect is resolved against the current host, so the same value works
    // locally and in production - the full URL just has to be listed under
    // "Authorised redirect URIs" in Google Cloud Console.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // UK address lookup (findaddress.io) for the shop address forms. Only
    // ever called from our server (AddressLookupController), so the key never
    // reaches the browser.
    'findaddress' => [
        'key' => env('FINDADDRESS_API_KEY'),
    ],

    // Stripe Checkout for owners ordering products (the counter display).
    // Only the secret key is needed - payment happens on Stripe's own page.
    // The webhook secret comes from the endpoint added in Stripe's dashboard
    // (or `stripe listen` locally).
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
