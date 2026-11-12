<?php

namespace App\Console\Commands;

use App\Models\HabitCompletion;
use App\Models\ModuleSetting;
use App\Models\UserActiveDay;
use App\Services\Experience\PersonalExperience;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Aggregate read-only report. No intentions, names, emails or record contents. */
class ExperienceReport extends Command
{
    protected $signature = 'experience:report';

    protected $description = 'Resume activación y regreso por contexto, sin datos personales ni escrituras';

    public function handle(): int
    {
        $groups = [];
        foreach (ModuleSetting::withoutGlobalScopes()->where('module', PersonalExperience::KEY)->cursor() as $row) {
            $state = $row->settings;
            if (empty($state['started_at'])) {
                continue;
            }
            $key = ($state['required'] ?? false) ? ($state['context'] ?? 'pending') : 'existing';
            $groups[$key] ??= ['started' => 0, 'completed' => 0, 'habit' => 0, 'action' => 0, 'd1_eligible' => 0, 'd1_returned' => 0, 'week_eligible' => 0, 'week_returned' => 0, 'habit_users' => 0];
            $g = &$groups[$key];
            $g['started']++;
            $g['completed'] += isset($state['completed_at']) ? 1 : 0;
            $g['habit'] += isset($state['events']['habit_created']) ? 1 : 0;
            $g['action'] += isset($state['events']['first_real_action']) ? 1 : 0;
            $start = CarbonImmutable::parse($state['started_at'])->timezone(config('app.timezone'))->startOfDay();
            $days = UserActiveDay::where('user_id', $row->user_id)->whereBetween('date', [$start->addDay()->toDateString(), $start->addDays(7)->toDateString()])->pluck('date')->map(fn ($date) => $date->toDateString());
            if (now()->startOfDay()->greaterThan($start->addDay())) {
                $g['d1_eligible']++;
                $g['d1_returned'] += $days->contains($start->addDay()->toDateString()) ? 1 : 0;
            }
            if (now()->startOfDay()->greaterThan($start->addDays(7))) {
                $g['week_eligible']++;
                $g['week_returned'] += $days->isNotEmpty() ? 1 : 0;
            }
            $g['habit_users'] += HabitCompletion::withoutGlobalScopes()->where('user_id', $row->user_id)
                ->where('completed', true)->whereDate('date', '>=', $start->toDateString())->exists() ? 1 : 0;
            unset($g);
        }
        $rows = [];
        foreach ($groups as $context => $g) {
            $rows[] = [$context, $g['started'], $g['completed'], $g['habit'], $g['action'], $g['d1_returned'].'/'.$g['d1_eligible'], $g['week_returned'].'/'.$g['week_eligible'], $g['habit_users']];
        }
        $this->table(['Contexto', 'Inicio guía', 'Fin guía', 'Hábito creado', 'Acción real', 'D1 regreso/elegibles', 'D1–7 regreso/elegibles', 'Usuarios con cumplimiento'], $rows);
        $this->line('Visitas y acciones son señales distintas. D1 y D1–7 solo incluyen ventanas completas.');

        return self::SUCCESS;
    }
}
