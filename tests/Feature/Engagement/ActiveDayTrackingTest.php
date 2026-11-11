<?php

namespace Tests\Feature\Engagement;

use App\Models\Game\GameEvent;
use App\Models\User;
use App\Models\UserActiveDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-066: días activos, canal de entrada, registros y reporte de retención. */
class ActiveDayTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_several_visits_leave_one_row_with_first_channel(): void
    {
        $this->actingAs($this->user)->get('/?via=push');
        $this->actingAs($this->user)->get('/');

        $days = UserActiveDay::where('user_id', $this->user->id)->get();
        $this->assertCount(1, $days);
        $this->assertSame('push', $days->first()->first_channel);
    }

    public function test_unknown_channel_is_direct(): void
    {
        UserActiveDay::touch($this->user->id, UserActiveDay::channel('spam'));

        $this->assertSame('direct', UserActiveDay::first()->first_channel);
    }

    public function test_activity_event_counts_as_record(): void
    {
        foreach ([1, 2] as $i) {
            GameEvent::withoutGlobalScopes()->create([
                'user_id' => $this->user->id, 'kind' => GameEvent::KIND_ACTIVITY, 'source_label' => 'Hábito',
                'idempotency_key' => "test:{$i}", 'occurred_at' => now(), 'effect' => [],
            ]);
        }
        GameEvent::withoutGlobalScopes()->create([
            'user_id' => $this->user->id, 'kind' => GameEvent::KIND_XP_GAINED, 'source_label' => 'XP',
            'idempotency_key' => 'test:xp', 'occurred_at' => now(), 'effect' => [],
        ]);

        $this->assertSame(2, UserActiveDay::where('user_id', $this->user->id)->value('records_count'));
    }

    public function test_retention_report_counts_returning_days(): void
    {
        UserActiveDay::touch($this->user->id, 'direct', '2026-11-01');
        UserActiveDay::touch($this->user->id, 'push', '2026-11-02');

        $this->artisan('miso:retention', ['--days' => 30])
            ->expectsOutputToContain('usuarios activos: 1')
            ->assertSuccessful();
    }
}
