<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** CHG-068: crea el aviso del guardián de la franja actual. Idempotente por (usuario, fecha, franja). */
class GuardianNudges extends Command
{
    protected $signature = 'miso:guardian-nudges {--user= : ID del usuario} {--dry-run : Muestra qué se crearía sin escribir}';

    protected $description = 'Crea el aviso del guardián de la franja actual para quien tenga algo pendiente';

    public function handle(GuardianNudgePlanner $planner): int
    {
        $counts = [];
        User::query()
            ->whereIn('id', PushSubscription::select('user_id'))
            ->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))
            ->eachById(function (User $user) use ($planner, &$counts) {
                $result = $planner->run($user, now(), (bool) $this->option('dry-run'));
                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
                if ($this->option('dry-run') && isset($result['body'])) {
                    $this->line("#{$user->id} {$result['module']}: {$result['title']} — {$result['body']}");
                }
            });

        if (! $this->option('dry-run')) {
            Cache::forever('miso:last-run:guardian-nudges', now()->toIso8601String());
        }
        $this->info('Avisos del guardián: '.($counts ? collect($counts)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ') : 'sin usuarios con avisos activos'));

        return self::SUCCESS;
    }
}
