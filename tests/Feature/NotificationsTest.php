<?php

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationBell;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\CreateNotification;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_idempotent_and_scoped_to_source(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Source']);
        $otherTask = Task::create(['title' => 'Other source']);
        $create = app(CreateNotification::class);
        $notice = $create->create($user, $task, 'reminder', 'same', 'Aviso', 'Texto', now());
        $this->assertNull($create->create($user, $task, 'reminder', 'same', 'Aviso', 'Texto', now()));
        $this->assertNotNull($create->create($user, $otherTask, 'reminder', 'same', 'Aviso', 'Texto', now()));
        $notice->update(['read_at' => now('UTC'), 'cancelled_at' => now('UTC')]);
        $create->create($user, $task, 'reminder', 'same', 'Aviso', 'Texto', now());
        $this->assertNull($notice->fresh()->cancelled_at);
        $this->assertNotNull($notice->fresh()->read_at);
        $this->assertNull($notice->fresh()->telegram_sent_at);
        $this->assertSame(2, UserNotification::count());
    }

    public function test_bell_isolates_users_and_does_not_mark_read_on_open(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Source']);
        $this->actingAs($other);
        $otherTask = Task::create(['title' => 'Other source']);
        $create = app(CreateNotification::class);
        $mine = $create->create($user, $task, 'reminder', 'mine', 'Mi aviso', 'Texto', now()->subMinute());
        $theirs = $create->create($other, $otherTask, 'reminder', 'other', 'Ajeno', 'Texto', now()->subMinute());
        $future = $create->create($user, $task, 'reminder', 'future', 'Futuro', 'Texto', now()->addHour());
        $this->actingAs($user);
        Livewire::test(NotificationBell::class)->set('open', true)->assertSee('Mi aviso')->assertDontSee('Ajeno')->assertDontSee('Futuro')
            ->call('markRead', $theirs->id)->call('markRead', $future->id);
        $this->assertNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
        $this->assertNull($future->fresh()->read_at);
        Livewire::test(NotificationBell::class)->call('markAllRead');
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull(UserNotification::withoutGlobalScopes()->find($theirs->id)->read_at);
        $this->get(route('settings.section', 'notifications'))->assertOk()->assertSee('Notificaciones:');
    }

    public function test_notification_instants_round_trip_in_utc(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Source']);
        $notice = app(CreateNotification::class)->create($user, $task, 'reminder', 'utc', 'Aviso', 'Texto', CarbonImmutable::parse('2026-10-24 09:00', 'America/Bogota'));
        $this->assertSame('2026-10-24 14:00', $notice->fresh()->scheduled_for->format('Y-m-d H:i'));
        $this->assertSame('09:00', $notice->fresh()->scheduled_for->timezone('America/Bogota')->format('H:i'));
    }
}
