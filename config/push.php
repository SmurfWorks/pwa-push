<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin token
    |--------------------------------------------------------------------------
    |
    | Registers and removes apps. Phones never use this token. Each app gets
    | its own token from push-apps:create.
    |
    */

    'admin_token' => env('PUSH_ADMIN_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | VAPID
    |--------------------------------------------------------------------------
    |
    | One key pair for every app. The public key is what a site passes to
    | pushManager.subscribe. The private key stays on this server.
    |
    */

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:pwa-push@localhost'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
