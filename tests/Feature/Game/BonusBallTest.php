<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\User;
use App\Services\Game\GameFeed;
use App\Services\Game\RewardGranter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-076 · T0: ball extra común con tope semanal y aviso `bonus`. */
class BonusBallTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->gameSettings(['daily_limit' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function grant(string $key): ?GameEncounter
    {
        return app(RewardGranter::class)->grantBonusBall(auth()->id(), $key, 'Racha de 7 días', ['title' => '¡7 días seguidos!', 'detail' => 'Ganaste una ball.']);
    }

    public function test_bonus_ball_ignores_daily_limit_and_is_idempotent(): void
    {
        $this->assertNotNull($this->grant('streak:7'));
        $this->assertNull($this->grant('streak:7'));

        $this->assertSame(1, GameEncounter::count());
        $this->assertSame(1, GameEvent::where('kind', GameEvent::KIND_BONUS)->count());
    }

    public function test_weekly_cap_records_the_bonus_without_a_ball(): void
    {
        config(['game.bonus_balls_per_week' => 2]);

        $this->grant('a');
        $this->grant('b');
        $this->assertNull($this->grant('c'));

        $this->assertSame(2, GameEncounter::count());
        $capped = GameEvent::where('idempotency_key', 'c')->first();
        $this->assertFalse($capped->effect['ball']);
        $this->assertSame('weekly_cap', $capped->effect['reason']);

        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'America/Bogota'));
        $this->assertNotNull($this->grant('d'));
    }

    public function test_disabled_game_grants_nothing(): void
    {
        $this->gameSettings(['enabled' => false]);

        $this->assertNull($this->grant('streak:3'));
        $this->assertSame(0, GameEvent::count());
    }

    public function test_feed_shows_a_bonus_toast(): void
    {
        $this->grant('streak:7');

        $feed = app(GameFeed::class)->since(auth()->id(), 0);

        $this->assertTrue($feed['celebrate']);
        $this->assertSame('bonus', $feed['toasts'][0]['type']);
        $this->assertSame('¡7 días seguidos!', $feed['toasts'][0]['title']);
        $this->assertSame('ball', $feed['toasts'][0]['action']);
    }
}
