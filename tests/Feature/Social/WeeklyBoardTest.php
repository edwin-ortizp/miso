<?php

namespace Tests\Feature\Social;

use App\Models\Game\GameEvent;
use App\Models\User;
use App\Services\Social\FriendSnapshot;
use App\Services\Social\SocialSettings;
use Carbon\Carbon;
use Livewire\Livewire;

class WeeklyBoardTest extends FriendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Viernes 2026-11-20: la semana va del lunes 16 al domingo 22 (America/Bogota).
        Carbon::setTestNow('2026-11-20 10:00:00', 'America/Bogota');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function share(User $user, bool $visible = true, bool $board = true): void
    {
        app(SocialSettings::class)->setVisible($user->id, $visible);
        app(SocialSettings::class)->setLeaderboard($user->id, $board);
    }

    private function actions(User $user, int $count, string $date = '2026-11-19 09:00:00', string $source = 'tasks'): void
    {
        for ($i = 0; $i < $count; $i++) {
            GameEvent::withoutGlobalScopes()->create([
                'user_id' => $user->id, 'kind' => GameEvent::KIND_ACTIVITY, 'effect' => ['source' => $source, 'ball' => false],
                'idempotency_key' => "t:{$source}:{$date}:{$i}", 'occurred_at' => Carbon::parse($date, 'America/Bogota'),
            ]);
        }
    }

    public function test_only_double_opt_in_friends_appear_with_their_count(): void
    {
        [$ana, $luis, $sofi, $oculto] = User::factory()->count(4)->create();
        foreach ([$luis, $sofi, $oculto] as $friend) {
            $this->befriend($ana, $friend);
        }
        $this->share($ana);
        $this->share($luis);
        $this->share($sofi, board: false);
        $this->share($oculto, visible: false);
        $this->actions($ana, 2);
        $this->actions($luis, 3, source: 'habits');
        $this->actions($sofi, 9);

        $rows = app(FriendSnapshot::class)->weekly($ana);

        $this->assertSame([$luis->id, $ana->id], array_column($rows, 'id'));
        $this->assertSame([3, 2], array_column($rows, 'actions'));
        $this->assertSame(['id', 'name', 'username', 'avatar_url', 'actions', 'me'], array_keys($rows[0]), 'solo un número por persona');
    }

    public function test_without_my_own_opt_in_the_board_is_empty(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $this->befriend($ana, $luis);
        $this->share($luis);

        $this->assertSame([], app(FriendSnapshot::class)->weekly($ana));
        $this->share($ana, board: false);
        $this->assertSame([], app(FriendSnapshot::class)->weekly($ana));
    }

    public function test_new_week_starts_from_zero_and_other_weeks_do_not_count(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $this->befriend($ana, $luis);
        $this->share($ana);
        $this->share($luis);
        $this->actions($luis, 4, '2026-11-15 20:00:00'); // domingo anterior
        $this->actions($luis, 1, '2026-11-16 00:30:00'); // lunes de esta semana

        $rows = app(FriendSnapshot::class)->weekly($ana);
        $this->assertSame(1, collect($rows)->firstWhere('id', $luis->id)['actions']);
        $this->assertSame(0, collect($rows)->firstWhere('id', $ana->id)['actions']);
    }

    public function test_non_activity_events_and_non_friends_are_ignored(): void
    {
        [$ana, $luis, $tercero] = User::factory()->count(3)->create();
        $this->befriend($ana, $luis);
        foreach ([$ana, $luis, $tercero] as $user) {
            $this->share($user);
        }
        GameEvent::withoutGlobalScopes()->create([
            'user_id' => $luis->id, 'kind' => GameEvent::KIND_BONUS, 'effect' => ['ball' => false],
            'idempotency_key' => 'b:1', 'occurred_at' => now(),
        ]);

        $rows = app(FriendSnapshot::class)->weekly($ana);
        $this->assertEqualsCanonicalizing([$ana->id, $luis->id], array_column($rows, 'id'));
        $this->assertNotContains($tercero->id, array_column($rows, 'id'));
        $this->assertSame(0, collect($rows)->firstWhere('id', $luis->id)['actions']);
    }

    public function test_page_toggles_and_renders(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create(['name' => 'Persona']);
        $this->befriend($ana, $luis);
        $this->share($luis);
        $this->actions($luis, 2);

        Livewire::actingAs($ana)->test(\App\Livewire\Social\WeeklyBoard::class)
            ->assertSee('Activa tu semana para verla')
            ->call('toggleVisible')->call('toggleLeaderboard')
            ->assertSee('2 acciones');
        $this->assertTrue(app(SocialSettings::class)->leaderboard($ana->id));
    }
}
