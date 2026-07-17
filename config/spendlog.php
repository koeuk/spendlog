<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super admin
    |--------------------------------------------------------------------------
    |
    | The owner account UserSeeder creates. It is out of reach of the
    | user-management screen, so it cannot be created through the app — this is
    | the only way in, and it needs server access to use.
    |
    | The password is applied once, when the account is first created. Changing
    | it here later does nothing to an existing account; use the profile page.
    | Set SUPER_ADMIN_PASSWORD in .env before seeding anywhere that matters —
    | the default below is a development convenience and is not a secret.
    |
    */

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'koeukkos@gmail.com'),
        'name' => env('SUPER_ADMIN_NAME', 'Koeuk'),
        'password' => env('SUPER_ADMIN_PASSWORD', '12345678'),
    ],

];
