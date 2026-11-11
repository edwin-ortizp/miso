<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationSource;
use App\Services\Notifications\Push\PushGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** CHG-067: diagnóstico del canal push en el servidor (hosting compartido). */
class PushCheck extends Command
{
    protected $signature = 'miso:push-check {--skip-network : No prueba la salida HTTPS}';

    protected $description = 'Revisa extensiones, claves VAPID, zona horaria, salida a los servicios push y la última corrida del cron';

    private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];

    public function handle(PushGateway $gateway): int
    {
        $ok = true;
        $row = function (string $label, bool $pass, string $detail = '') use (&$ok) {
            $ok = $ok && $pass;
            $this->line(($pass ? '<info>OK</info>   ' : '<error>FALLA</error> ').$label.($detail ? " — {$detail}" : ''));
        };

        $row('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.4.0', '>='), 'el proyecto requiere PHP 8.4 en la línea de comandos');
        foreach (['openssl', 'curl', 'mbstring'] as $ext) {
            $row("Extensión {$ext}", extension_loaded($ext));
        }
        $row('Extensión gmp o bcmath (opcional, más rápido)', true, extension_loaded('gmp') ? 'gmp' : (extension_loaded('bcmath') ? 'bcmath' : 'no está; funciona igual'));
        $problem = $gateway->problem();
        $row('Claves VAPID', $problem === null, $problem ?? '');
        $row('Hora de las franjas', true, now(NotificationSource::TIMEZONE)->format('Y-m-d H:i').' '.NotificationSource::TIMEZONE);

        if (! $this->option('skip-network')) {
            foreach (self::HOSTS as $host) {
                try {
                    $errno = 0;
                    $errstr = '';
                    $socket = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 5);
                    $row("Salida HTTPS a {$host}", $socket !== false, $socket ? '' : "{$errstr} ({$errno}); pide al hosting habilitar salida al puerto 443");
                    $socket && fclose($socket);
                } catch (Throwable $e) {
                    $row("Salida HTTPS a {$host}", false, $e->getMessage());
                }
            }
        }

        foreach (['guardian-nudges' => 'miso:guardian-nudges', 'dispatch' => 'notifications:dispatch'] as $key => $command) {
            $last = Cache::get("miso:last-run:{$key}");
            $row("Última corrida de {$command}", $last !== null, $last ?? 'nunca: revisa la línea de cron (docs/DEPLOY.md)');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
