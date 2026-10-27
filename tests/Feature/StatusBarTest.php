<?php

namespace Tests\Feature;

use App\Livewire\StatusBar\StatusBar;
use App\Models\PomodoroSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StatusBarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_shows_only_todays_focus_of_the_authenticated_user(): void
    {
        Carbon::setTestNow('2026-07-13 18:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->focusSession($user, '2026-07-13', 5400);
        $this->focusSession($user, '2026-07-12', 3600);
        $this->focusSession($other, '2026-07-13', 7200);

        Livewire::actingAs($user)
            ->test(StatusBar::class)
            ->assertSee('Foco hoy: 1h 30m / 5h');
    }

    public function test_saving_a_timer_session_refreshes_the_total(): void
    {
        Carbon::setTestNow('2026-07-13 18:00:00');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StatusBar::class)
            ->assertSee('Foco hoy: 0m / 5h')
            ->call('saveSession', now()->subMinutes(25)->timestamp, now()->timestamp, 'Revisar PR', (string) Str::uuid())
            ->assertDispatched('focus-session-saved')
            ->assertSee('Foco hoy: 25m / 5h');

        $this->assertSame($user->id, PomodoroSession::firstOrFail()->user_id);
    }

    public function test_tasks_page_renders_status_bar_with_module_shortcuts(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tasks.list'))
            ->assertOk()
            ->assertSee('Barra de estado')
            ->assertSee('Iniciar foco');
    }

    private function focusSession(User $user, string $date, int $duration): void
    {
        PomodoroSession::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->id, 'date' => $date, 'duration' => $duration, 'completed' => true,
            'start_time' => ['timestamp' => 0], 'end_time' => ['timestamp' => 0],
        ]);
    }
}
