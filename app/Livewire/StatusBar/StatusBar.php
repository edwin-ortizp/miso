<?php

namespace App\Livewire\StatusBar;

use App\Livewire\Pomodoro\PomodoroTimer;
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
        $settings = app(\App\Support\PomodoroSettings::class)->for();

        $this->workDuration = $settings['work_duration_minutes'];
        $this->shortBreak = $settings['short_break_minutes'];
        $this->longBreak = $settings['long_break_minutes'];
        $this->weekdayGoal = $settings['weekday_goal_minutes'];
        $this->weekendGoal = $settings['weekend_goal_minutes'];
    }

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken, ?string $taskId = null): void
    {
        PomodoroTimer::storeTimerSession($startedAt, $endedAt, $description, $clientToken, $taskId);
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
