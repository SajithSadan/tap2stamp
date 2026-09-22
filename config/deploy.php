<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deploy migrate token
    |--------------------------------------------------------------------------
    |
    | Bearer token required by POST /deploy/migrate (see DeployController).
    | Generate a strong random value for production, e.g.:
    |   php artisan tinker --execute="echo Str::random(64);"
    | Leave blank locally — the route refuses to run without one configured.
    |
    */
    'migrate_token' => env('DEPLOY_MIGRATE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Admin bootstrap
    |--------------------------------------------------------------------------
    |
    | Credentials for the one admin account created by POST /deploy/seed-admin
    | (same bearer token as above). Leave blank locally - DatabaseSeeder
    | creates a dev admin instead. Idempotent: won't overwrite an existing
    | admin's password if these change later.
    |
    */
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),
];
