<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID as VapidKeys;

/** CHG-067: genera las claves VAPID una sola vez. No escribe .env: se copian a mano. */
class Vapid extends Command
{
    protected $signature = 'miso:vapid';

    protected $description = 'Genera un par de claves VAPID para los avisos push';

    public function handle(): int
    {
        if (config('webpush.public_key')) {
            $this->warn('Ya hay VAPID_PUBLIC_KEY en .env. Cambiarla invalida las suscripciones actuales: cada usuario tendría que activar los avisos otra vez.');
            if (! $this->confirm('¿Generar otro par de todos modos?', false)) {
                return self::SUCCESS;
            }
        }
        $keys = VapidKeys::createVapidKeys();
        $this->line('Copia estas líneas en .env y luego ejecuta php artisan config:clear:');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:tu-correo@ejemplo.com');

        return self::SUCCESS;
    }
}
