<?php

namespace App\Livewire\Concerns;

use App\Services\Notifications\ManageReminders;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

trait HasReminderDraft
{
    public array $reminders = [];

    public function addReminder(): void
    {
        $this->reminders[] = ['amount' => 1, 'unit' => 'days'];
    }

    public function removeReminder(int $index): void
    {
        unset($this->reminders[$index]);
        $this->reminders = array_values($this->reminders);
    }

    protected function loadReminders(?Model $source): void
    {
        $this->reminders = $source ? app(ManageReminders::class)->rows($source) : [];
    }

    protected function validateReminders(?string $date, ?string $time = null): void
    {
        $manager = app(ManageReminders::class);
        $manager->validate($this->reminders, filled($date));
        if ($date) {
            $anchor = CarbonImmutable::parse($date.' '.($time ?: '09:00'), 'America/Bogota');
            foreach ($this->reminders as $row) {
                $manager->calculate($anchor, (int) $row['amount'], $row['unit']);
            }
        }
    }

    protected function saveReminders(Model $source): void
    {
        abort_unless((int) $source->user_id === (int) auth()->id(), 404);
        app(ManageReminders::class)->sync($source, $this->reminders);
    }
}
