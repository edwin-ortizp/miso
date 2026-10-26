<?php

namespace Tests\Feature;

use App\Livewire\Pomodoro\PomodoroSettings;
use App\Livewire\Pomodoro\PomodoroTimer;
use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PomodoroTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manual_session_uses_real_interval_and_updates_progress(): void
    {
        Carbon::setTestNow('2026-07-13 10:00:00');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->set('selectedDate', '2026-07-10')
            ->call('openForm')
            ->set('manualStart', '2026-07-13T08:30')
            ->set('manualEnd', '2026-07-13T10:00')
            ->set('manualDescription', 'Preparar entrega')
            ->assertSee('Duración calculada:')
            ->call('saveManualSession')
            ->assertSet('selectedDate', '2026-07-13')
            ->assertSee('1 h 30 min')
            ->assertSee('30%');

        $session = PomodoroSession::firstOrFail();
        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('2026-07-13', $session->date->toDateString());
        $this->assertSame(5400, $session->duration);
        $this->assertSame(Carbon::parse('2026-07-13 08:30')->timestamp, $session->start_time['timestamp']);
        $this->assertSame(Carbon::parse('2026-07-13 10:00')->timestamp, $session->end_time['timestamp']);
        $this->assertTrue($session->completed);
        $this->assertSame('Preparar entrega', $session->description);
    }

    public function test_manual_session_can_cross_midnight_and_uses_start_date(): void
    {
        Carbon::setTestNow('2026-07-14 08:00:00');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->set('manualStart', '2026-07-13T23:30')
            ->set('manualEnd', '2026-07-14T01:00')
            ->call('saveManualSession')
            ->assertHasNoErrors();

        $session = PomodoroSession::firstOrFail();
        $this->assertSame('2026-07-13', $session->date->toDateString());
        $this->assertSame(5400, $session->duration);
    }

    public function test_manual_session_rejects_invalid_future_and_overlong_intervals(): void
    {
        Carbon::setTestNow('2026-07-14 08:00:00');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->set('manualStart', '2026-07-14T07:30')
            ->set('manualEnd', '2026-07-14T07:00')
            ->call('saveManualSession')
            ->assertHasErrors(['manualEnd'])
            ->set('manualEnd', '2026-07-14T09:00')
            ->call('saveManualSession')
            ->assertHasErrors(['manualEnd'])
            ->set('manualStart', '2026-07-13T14:00')
            ->set('manualEnd', '2026-07-14T07:00')
            ->call('saveManualSession')
            ->assertHasErrors(['manualEnd']);

        $this->assertDatabaseCount('pomodoro_sessions', 0);
    }

    public function test_weekend_goal_is_used_and_settings_are_saved_per_user(): void
    {
        Carbon::setTestNow('2026-07-13 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        PomodoroSession::create([
            'date' => '2026-07-12',
            'start_time' => ['timestamp' => Carbon::parse('2026-07-12 08:00')->timestamp],
            'end_time' => ['timestamp' => Carbon::parse('2026-07-12 09:00')->timestamp],
            'duration' => 3600,
            'completed' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->set('selectedDate', '2026-07-12')
            ->assertSee('Meta: 120 min')
            ->assertSee('50%');

        Livewire::actingAs($user)
            ->test(PomodoroSettings::class)
            ->assertSet('workDuration', 25)
            ->set('workDuration', 50)
            ->set('shortBreak', 10)
            ->set('longBreak', 20)
            ->set('weekdayGoal', 360)
            ->set('weekendGoal', 180)
            ->call('save')
            ->assertSee('Ajustes de Pomodoro actualizados.');

        $setting = ModuleSetting::where('module', 'pomodoro')->firstOrFail();
        $this->assertSame(50, $setting->settings['work_duration_minutes']);
        $this->assertSame(10, $setting->settings['short_break_minutes']);
        $this->assertSame(20, $setting->settings['long_break_minutes']);
        $this->assertSame(360, $setting->settings['weekday_goal_minutes']);
        $this->assertSame(180, $setting->settings['weekend_goal_minutes']);

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->assertSet('workDuration', 50)
            ->assertSet('shortBreak', 10)
            ->assertSet('longBreak', 20);
    }

    public function test_timer_session_uses_real_timestamps_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-13 10:00:00');
        $user = User::factory()->create();

        $start = Carbon::parse('2026-07-13 09:35')->timestamp;
        $end = Carbon::parse('2026-07-13 10:00')->timestamp;
        $token = 'e59067e6-7051-4b66-9e64-2f39e467a7b8';

        $component = Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->set('selectedDate', '2026-07-10')
            ->call('saveSession', $start, $end, 'Trabajo concentrado', $token)
            ->assertSet('selectedDate', '2026-07-13');

        $component->call('saveSession', $start, $end, 'Trabajo concentrado', $token);

        $session = PomodoroSession::firstOrFail();
        $this->assertDatabaseCount('pomodoro_sessions', 1);
        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('2026-07-13', $session->date->toDateString());
        $this->assertSame(1500, $session->duration);
        $this->assertSame('Trabajo concentrado', $session->description);
        $this->assertSame($token, $session->client_token);
    }

    public function test_pomodoro_routes_require_authentication_and_render_tabs(): void
    {
        $this->get('/pomodoro')->assertRedirect('/login');
        $this->get('/pomodoro/settings')->assertRedirect('/login');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pomodoro')
            ->assertOk()
            ->assertSee('Temporizador')
            ->assertSee('Ajustes')
            ->assertDontSee('Día consultado');

        $this->actingAs($user)
            ->get('/pomodoro/settings')
            ->assertOk()
            ->assertSee('Duración del temporizador')
            ->assertSee('Metas de trabajo');
    }

    public function test_duration_migration_normalizes_millisecond_timestamps(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $session = PomodoroSession::create([
            'date' => '2025-01-17',
            'start_time' => ['timestamp' => 1737122400000],
            'end_time' => ['timestamp' => 1737124200000],
            'duration' => 30,
            'completed' => true,
        ]);

        $migration = require database_path('migrations/2026_07_13_000001_normalize_pomodoro_session_durations_to_seconds.php');
        $migration->up();

        $this->assertSame(1800, $session->fresh()->duration);
    }

    public function test_session_can_be_edited_from_dialog_keeping_its_origin(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $token = 'e59067e6-7051-4b66-9e64-2f39e467a7b8';
        $session = PomodoroSession::create([
            'date' => '2026-07-13',
            'start_time' => ['timestamp' => Carbon::parse('2026-07-13 09:00')->timestamp],
            'end_time' => ['timestamp' => Carbon::parse('2026-07-13 09:25')->timestamp],
            'duration' => 1500,
            'completed' => true,
            'client_token' => $token,
        ]);

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->call('openForm', $session->id)
            ->assertSet('showForm', true)
            ->assertSee('Calendario del mes')
            ->assertSet('editingDate', '2026-07-13')
            ->assertSet('manualStart', '09:00')
            ->assertSet('manualEnd', '09:25')
            ->set('manualEnd', '10:00')
            ->set('manualDescription', 'Revisión')
            ->call('saveManualSession')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $session->refresh();
        $this->assertDatabaseCount('pomodoro_sessions', 1);
        $this->assertSame(3600, $session->duration);
        $this->assertSame('Revisión', $session->description);
        $this->assertSame($token, $session->client_token);
    }

    public function test_sessions_table_filters_by_origin_and_search(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['Informe', 'e59067e6-7051-4b66-9e64-2f39e467a7b8'], ['Lectura', null]] as [$description, $token]) {
            PomodoroSession::create([
                'date' => '2026-07-13',
                'start_time' => ['timestamp' => now()->subHour()->timestamp],
                'end_time' => ['timestamp' => now()->timestamp],
                'duration' => 3600,
                'completed' => true,
                'description' => $description,
                'client_token' => $token,
            ]);
        }

        Livewire::actingAs($user)
            ->test(PomodoroTimer::class)
            ->assertSee('Informe')->assertSee('Lectura')
            ->call('applyFilters', 'day', 'manual')
            ->assertSee('Lectura')->assertDontSee('Informe')
            ->call('clearFilters')
            ->set('search', 'Inf')
            ->assertSee('Informe')->assertDontSee('Lectura');
    }

    public function test_rail_shows_goal_calendar_with_weekday_and_weekend_goals(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pomodoro')
            ->assertOk()
            ->assertSee('Meta de hoy')
            ->assertSee('Calendario del mes')
            ->assertSee('12 de julio: sin registros', false)
            ->assertSee('Registrar tiempo');
    }

    public function test_user_cannot_see_another_users_sessions(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner);
        $session = PomodoroSession::create([
            'date' => '2026-07-13',
            'start_time' => ['timestamp' => now()->subMinutes(30)->timestamp],
            'end_time' => ['timestamp' => now()->timestamp],
            'duration' => 1800,
            'completed' => true,
        ]);
        ModuleSetting::create([
            'module' => 'pomodoro',
            'settings' => ['weekday_goal_minutes' => 420],
        ]);

        $this->actingAs($otherUser);

        $this->assertNull(PomodoroSession::find($session->id));
        $this->assertNull(ModuleSetting::where('module', 'pomodoro')->first());
        $this->assertSame($owner->id, $session->user_id);
    }
}
