<?php

namespace App\Livewire\StatusBar;

use App\Livewire\Pomodoro\PomodoroTimer;
use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bloque global de la barra de estado: foco de hoy frente a la meta y un
 * temporizador Pomodoro que comparte estado (localStorage) con el módulo.
 * Vive dentro de @persist, así que no depende del módulo activo.
 */
class StatusBar extends Component
{
    public int $workDuration = 25;

    public int $shortBreak = 5;

    public int $longBreak = 15;

    public int $weekdayGoal = 300;

    public int $weekendGoal = 120;

    public function mount(): void
    {
        $settings = ModuleSetting::where('module', 'pomodoro')->value('settings') ?? [];

        $this->workDuration = (int) ($settings['work_duration_minutes'] ?? 25);
        $this->shortBreak = (int) ($settings['short_break_minutes'] ?? 5);
        $this->longBreak = (int) ($settings['long_break_minutes'] ?? 15);
        $this->weekdayGoal = (int) ($settings['weekday_goal_minutes'] ?? 300);
        $this->weekendGoal = (int) ($settings['weekend_goal_minutes'] ?? 120);
    }

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken): void
    {
        PomodoroTimer::storeTimerSession($startedAt, $endedAt, $description, $clientToken);
        $this->dispatch('focus-session-saved');
    }

    #[On('focus-session-saved')]
    #[On('panel-changed')]
    public function refreshTotals(): void
    {
        // El re-render recalcula el total del día.
    }

    public function render()
    {
        $todayMinutes = intdiv((int) PomodoroSession::whereDate('date', today())->where('completed', true)->sum('duration'), 60);
        $goalMinutes = today()->isWeekend() ? $this->weekendGoal : $this->weekdayGoal;

        return view('livewire.status-bar.status-bar', [
            'todayLabel' => self::formatMinutes($todayMinutes),
            'goalLabel' => self::formatMinutes($goalMinutes),
            'goalReached' => $goalMinutes > 0 && $todayMinutes >= $goalMinutes,
        ]);
    }

    public static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? ($rest > 0 ? "{$hours}h {$rest}m" : "{$hours}h") : "{$rest}m";
    }
}
