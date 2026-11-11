<?php

namespace App\Livewire\Notifications;

use App\Models\PushSubscription;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Validator;
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

    /** CHG-067: el navegador entrega su suscripción push (PushSubscription.toJSON()). */
    public function saveSubscription(array $subscription): void
    {
        $data = Validator::make($subscription, [
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ])->validate();

        // El endpoint es único: si otro usuario lo tenía en este navegador, pasa a quien lo activa ahora.
        PushSubscription::updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => auth()->id(), 'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
        ]);
        $this->preferences['guardian_nudges'] = true;
        app(NotificationPreferences::class)->save(auth()->id(), $this->preferences);
        $this->message = 'Listo, tu guardián te escribirá en este dispositivo.';
    }

    public function removeSubscription(string $endpoint): void
    {
        PushSubscription::where('user_id', auth()->id())->where('endpoint', $endpoint)->delete();
        if (! PushSubscription::where('user_id', auth()->id())->exists()) {
            $this->preferences['guardian_nudges'] = false;
            app(NotificationPreferences::class)->save(auth()->id(), $this->preferences);
        }
        $this->message = 'Avisos desactivados en este dispositivo.';
    }

    public function render()
    {
        return view('livewire.notifications.settings', [
            'vapidKey' => (string) config('webpush.public_key'),
            'slots' => config('game.nudges.slots'),
            'devices' => PushSubscription::where('user_id', auth()->id())->count(),
        ]);
    }
}
