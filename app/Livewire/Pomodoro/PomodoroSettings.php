<?php

namespace App\Livewire\Pomodoro;

use App\Models\ModuleSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajustes de Pomodoro')]
class PomodoroSettings extends Component
{
    public int $workDuration = 25;

    public int $shortBreak = 5;

    public int $longBreak = 15;

    public int $weekdayGoal = 300;

    public int $weekendGoal = 120;

    public string $message = '';

    public function mount(): void
    {
        $settings = app(\App\Support\PomodoroSettings::class)->for();

        $this->workDuration = $settings['work_duration_minutes'];
        $this->shortBreak = $settings['short_break_minutes'];
        $this->longBreak = $settings['long_break_minutes'];
        $this->weekdayGoal = $settings['weekday_goal_minutes'];
        $this->weekendGoal = $settings['weekend_goal_minutes'];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'workDuration' => ['required', 'integer', 'between:1,60'],
            'shortBreak' => ['required', 'integer', 'between:1,30'],
            'longBreak' => ['required', 'integer', 'between:1,60'],
            'weekdayGoal' => ['required', 'integer', 'between:0,1440'],
            'weekendGoal' => ['required', 'integer', 'between:0,1440'],
        ]);

        $setting = ModuleSetting::firstOrCreate(['module' => 'pomodoro']);
        $setting->update([
            'settings' => array_merge($setting->settings ?? [], [
                'work_duration_minutes' => $validated['workDuration'],
                'short_break_minutes' => $validated['shortBreak'],
                'long_break_minutes' => $validated['longBreak'],
                'weekday_goal_minutes' => $validated['weekdayGoal'],
                'weekend_goal_minutes' => $validated['weekendGoal'],
            ]),
        ]);

        $this->message = 'Ajustes de Pomodoro actualizados.';
    }

    public function render()
    {
        return view('livewire.pomodoro.pomodoro-settings');
    }
}
