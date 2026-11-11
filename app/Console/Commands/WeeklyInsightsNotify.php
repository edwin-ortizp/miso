<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Insights\WeeklyInsights;
use Illuminate\Console\Command;

/** CHG-071: calcula los hallazgos de la semana y crea el aviso del domingo. Idempotente por semana. */
class WeeklyInsightsNotify extends Command
{
    protected $signature = 'miso:weekly-insights {--user= : ID del usuario}';

    protected $description = 'Calcula el resumen semanal y crea su aviso (una vez por semana)';

    public function handle(WeeklyInsights $insights): int
    {
        $created = 0;
        User::query()->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))
            ->eachById(function (User $user) use ($insights, &$created) {
                $created += $insights->notify($user, now()) ? 1 : 0;
            });
        $this->info("Avisos de resumen semanal creados: {$created}");

        return self::SUCCESS;
    }
}
