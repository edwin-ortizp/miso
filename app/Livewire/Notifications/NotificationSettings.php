<?php

namespace App\Livewire\Notifications;

use App\Services\Notifications\NotificationPreferences;
use Livewire\Component;

class NotificationSettings extends Component
{
    public array $preferences = [];

    public string $message = '';

    public function mount(NotificationPreferences $settings): void
    {
        $this->preferences = $settings->get(auth()->id());
    }

    public function save(): void
    {
        $this->validate(['preferences.*' => 'boolean']);
        app(NotificationPreferences::class)->save(auth()->id(), $this->preferences);
        $this->message = 'Preferencias guardadas.';
    }

    public function render()
    {
        return view('livewire.notifications.settings');
    }
}
