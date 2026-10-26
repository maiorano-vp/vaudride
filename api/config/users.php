<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin account
    |--------------------------------------------------------------------------
    |
    | Created by the `provision_admin_user` migration, so every environment gets the same admin on deploy.
    | Without ADMIN_PASSWORD the migration does nothing.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Vaud Promotion Digital'),
        'email' => env('ADMIN_EMAIL', 'digital@vaud-promotion.ch'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
