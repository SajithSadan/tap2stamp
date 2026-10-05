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

    // Gemini reads photos/PDFs of a shop's menu into sections and items
    // (Admin → shop → Menu). Server-side only (App\Services\MenuReader).
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        // Tried when the main model is overloaded (503 "high demand" / 429).
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-2.5-flash'),
    ],

    // WhatsApp Cloud API (Meta) for owners' marketing messages. One Tada Tap
    // business number sends for every shop, using one approved template -
    // its exact wording is App\Services\WhatsAppGateway::TEMPLATE_BODY.
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'shop_offer'),
        // Same body + button with an Image header, for messages with a poster.
        'template_image' => env('WHATSAPP_TEMPLATE_IMAGE', 'shop_offer_image'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_GB'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
