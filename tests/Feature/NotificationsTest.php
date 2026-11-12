<?php

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationBell;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\CreateNotification;
use App\Services\Notifications\NotificationPresentation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
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
        Livewire::test(NotificationBell::class)->set('open', true)->assertSee('Source')->assertDontSee('Other source')->assertDontSee('Futuro')
            ->assertSee('notification-'.$mine->id, false)->assertDontSee('notification-'.$future->id, false)
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

    public function test_filters_are_drafts_and_bulk_read_includes_other_pages_only_for_applied_filters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Tarea del filtro']);
        $create = app(CreateNotification::class);
        for ($i = 0; $i < 17; $i++) {
            $create->create($user, $task, 'reminder', 'filter-'.$i, 'Viejo', 'Texto', now()->subMinute());
        }
        $untouched = $create->create($user, $task, 'task_due', '2026-11-12 09:00:00', 'Viejo', 'Texto', now()->subMinute());
        $component = Livewire::test(NotificationBell::class)->set('open', true)
            ->set('draftReading', 'unread')->set('draftType', 'reminder')
            ->assertSet('reading', 'all')->assertSet('type', '')
            ->call('applyFilters')->assertSet('reading', 'unread')->assertSet('type', 'reminder');
        $component->call('markAllRead')->assertSee('No hay notificaciones con estos filtros.');
        $this->assertSame(17, UserNotification::where('type', 'reminder')->whereNotNull('read_at')->count());
        $this->assertNull($untouched->fresh()->read_at);
        $component->call('removeFilter', 'type')->assertSet('type', '')->assertSet('draftType', '')
            ->call('clearFilters')->assertSet('reading', 'all')->assertSet('draftReading', 'all');
    }

    public function test_reading_the_last_items_on_a_filtered_page_recovers_the_previous_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Pendiente']);
        $notices = collect();
        for ($i = 0; $i < 17; $i++) {
            $notices->push(app(CreateNotification::class)->create($user, $task, 'reminder', 'page-'.$i, 'Aviso', 'Texto', now()->subMinutes($i + 1)));
        }
        $component = Livewire::test(NotificationBell::class)->set('open', true)
            ->set('draftReading', 'unread')->call('applyFilters')->call('gotoPage', 2, 'notificationsPage');
        $component->call('markRead', $notices[15]->id)->call('markRead', $notices[16]->id)
            ->assertSet('paginators.notificationsPage', 1)->assertSee('Pendiente');
    }

    public function test_loading_failure_has_a_retry_that_recovers_without_marking_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $person = Relationship::create(['full_name' => 'Laura', 'category' => 'friend']);
        $notice = app(CreateNotification::class)->create($user, $person, 'birthday', today()->toDateString(), 'Cumpleaños', 'Texto', now()->subMinute());
        $presenter = app(NotificationPresentation::class);
        $mock = \Mockery::mock(NotificationPresentation::class);
        $mock->shouldReceive('forNotices')->once()->ordered()->andThrow(new QueryException('sqlite', 'select 1', [], new \PDOException('Fallo de prueba aislado')));
        $mock->shouldReceive('forNotices')->once()->ordered()->andReturnUsing(fn ($rows, $userId) => $presenter->forNotices($rows, $userId));
        $this->app->instance(NotificationPresentation::class, $mock);
        Livewire::test(NotificationBell::class)->set('open', true)->assertSee('No pudimos cargar las notificaciones')->assertSee('Reintentar')
            ->call('$refresh')->assertSee('Cumpleaños de Laura')->assertDontSee('No pudimos cargar las notificaciones');
        $this->assertNull($notice->fresh()->read_at);
    }
}
