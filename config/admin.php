<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single Admin Account
    |--------------------------------------------------------------------------
    |
    | Credentials for the single seeded admin account. Used by AdminSeeder and
    | the app:set-admin-password command. Reading these through config (rather
    | than env() directly) keeps them available under a cached config.
    |
    */

    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),

];
