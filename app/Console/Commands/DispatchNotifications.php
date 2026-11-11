<?php

namespace App\Console\Commands;

use App\Services\Notifications\Push\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** CHG-068: entrega por push las notificaciones disponibles. Cada una se envía una sola vez. */
class DispatchNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--dry-run : Cuenta lo que se enviaría sin enviar ni marcar}';

    protected $description = 'Envía por push las notificaciones disponibles que aún no se han entregado';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $r = $dispatcher->run((bool) $this->option('dry-run'));
        if ($r['problem']) {
            $this->warn('Push sin configurar: '.$r['problem']);

            return self::SUCCESS;
        }
        if (! $this->option('dry-run')) {
            Cache::forever('miso:last-run:dispatch', now()->toIso8601String());
        }
        $this->info(sprintf('Enviadas: %d · fallidas: %d · omitidas por preferencia: %d · suscripciones vencidas: %d%s',
            $r['sent'], $r['failed'], $r['skipped'], $r['expired'], $r['stopped'] ? ' · se detuvo por límite, sigue en la próxima corrida' : ''));

        return self::SUCCESS;
    }
}
