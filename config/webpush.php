<?php

/** Canal push (CHG-067). Claves VAPID: `php artisan miso:vapid` (ver docs/DEPLOY.md). */
return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', env('APP_URL')),
    // Un envío no debería tardar más: el hosting compartido corta procesos largos.
    'timeout' => 10,
];
