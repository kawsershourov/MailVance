<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public self-registration
    |--------------------------------------------------------------------------
    |
    | Off by default. A bulk-email platform with an open signup form is an
    | abuse magnet: anyone can mint an account, attach their own SMTP relay and
    | send through your infrastructure and domain reputation. Leave this false
    | and create accounts from Admin -> Users, or turn it on deliberately.
    |
    */

    'allow_registration' => env('ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | SMTP relay egress
    |--------------------------------------------------------------------------
    |
    | Users supply their own relay host and port, and the diagnostics screen
    | opens a socket to it. Without limits that is a port scanner pointed at
    | your internal network, so connections are confined to real mail ports and
    | to hosts that do not resolve into private space.
    |
    */

    'smtp' => [
        'allowed_ports' => [25, 465, 587, 2525],

        // Set false only for a single-tenant install where relays legitimately
        // live on the same private network as the app.
        'block_private_hosts' => env('SMTP_BLOCK_PRIVATE_HOSTS', true),
    ],

];
