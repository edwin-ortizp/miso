<?php

namespace App\Console\Commands;

use App\Services\Notifications\Android\AndroidDispatcher;
use App\Services\Notifications\Android\FcmGateway;
use Illuminate\Console\Command;

class AndroidPushCheck extends Command
{
    protected $signature = 'miso:android-push-check {--dry-run : Cuenta entregas elegibles sin enviar ni escribir}';

    protected $description = 'Diagnostica el canal Android sin mostrar credenciales ni tokens';

    public function handle(FcmGateway $gateway, AndroidDispatcher $dispatcher): int
    {
        if ($problem = $gateway->problem()) {
            $this->warn($problem);

            return self::SUCCESS;
        }
        $this->info('FCM configurado. Esto no certifica una entrega al teléfono.');
        if ($this->option('dry-run')) {
            $r = $dispatcher->run(true);
            $this->info('Entregas elegibles: '.$r['sent']);
        }

        return self::SUCCESS;
    }
}
