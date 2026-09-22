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
];
