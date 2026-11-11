<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSeasonNode;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\MissionProposalValidator;
use App\Services\Game\SeasonPath;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** CHG-079: puntos, paradas y recompensas del camino de temporada. */
class SeasonPathTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private GameSeason $season;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        // 4 semanas → objetivo 80; paradas en 4, 12, 24, 36, 48, 64 y 80 puntos.
        $this->season = GameSeason::create([
            'user_id' => auth()->id(), 'status' => GameSeason::ACTIVE, 'name' => 'Brisa de otoño', 'narrative' => 'x',
            'config' => [], 'source' => 'manual', 'starts_at' => now()->subDays(3)->startOfDay(), 'ends_at' => now()->subDays(3)->startOfDay()->addWeeks(4),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function acts(int $count, string $date = '2026-11-11', array $attributes = []): void
    {
        foreach (range(1, $count) as $_) {
            GameEvent::withoutGlobalScopes()->create([
                'user_id' => auth()->id(), 'idempotency_key' => Str::uuid()->toString(), 'kind' => GameEvent::KIND_ACTIVITY,
                'source_label' => 'x', 'effect' => ['source' => 'habits', 'ball' => false],
                'occurred_at' => Carbon::parse($date.' 09:00:00', 'America/Bogota'),
            ] + $attributes);
        }
    }

    private function path(): SeasonPath
    {
        return app(SeasonPath::class);
    }

    private function state(): array
    {
        return $this->path()->state(auth()->id(), $this->season->refresh());
    }

    public function test_points_are_activities_capped_per_day_plus_missions(): void
    {
        $this->acts(8, '2026-11-09');
        $this->acts(2, '2026-11-10');
        $week = GameWeek::create(['user_id' => auth()->id(), 'week_start' => WeekPlanner::weekStart(now()), 'seed' => 1, 'config_snapshot' => []]);
        GameMission::create(['user_id' => auth()->id(), 'game_week_id' => $week->id, 'type' => 'variety', 'title' => 'M', 'params' => [], 'target' => 2, 'progress' => 2, 'source' => 'system', 'full_rewarded_at' => now()]);

        $state = $this->state();
        $this->assertSame(5 + 2 + 3, $state['points']);
        $this->assertSame(80, $state['target']);
        $this->assertSame([4, 12, 24, 36, 48, 64, 80], array_column($state['nodes'], 'at'));
    }

    public function test_activities_before_the_season_and_same_day_tasks_do_not_count(): void
    {
        $this->acts(3, '2026-11-01');
        $farm = Task::create(['title' => 'Rápida']);
        $this->acts(1, '2026-11-11', ['source_type' => 'task', 'source_id' => (string) $farm->id]);

        $this->assertSame(0, $this->state()['points']);
    }

    public function test_reaching_stops_rewards_each_once_in_order(): void
    {
        $this->acts(5, '2026-11-09');
        $this->acts(5, '2026-11-10');
        $this->acts(3, '2026-11-11');
        $companion = GameCompanion::create(['user_id' => auth()->id(), 'species_id' => GameSpecies::first()->id, 'line_key' => 'brotelo', 'xp' => 0, 'level' => 1, 'first_met_at' => now()]);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());

        $this->path()->awardPending(auth()->id());
        $this->path()->awardPending(auth()->id());

        // 13 puntos: paradas 1 (ball) y 2 (+10 xp) cruzadas de golpe.
        $this->assertSame([1, 2], GameSeasonNode::orderBy('node')->pluck('node')->all());
        $this->assertSame(1, GameEvent::where('idempotency_key', 'like', "season:{$this->season->id}:node:1:%")->count());
        $this->assertTrue(GameEvent::where('idempotency_key', "season:{$this->season->id}:node:2:xp")->exists());
        $this->assertTrue($week->members()->where('companion_id', $companion->id)->exists());
        $this->assertSame(10, (int) $companion->refresh()->xp);
        $this->assertSame(3, $this->state()['next']['n']);
    }

    public function test_path_balls_do_not_add_points(): void
    {
        $this->acts(4);
        $this->path()->awardPending(auth()->id());

        $this->assertSame(4, $this->state()['points']);
    }

    public function test_reverted_season_freezes_and_awards_nothing_new(): void
    {
        $this->acts(4);
        $this->path()->awardPending(auth()->id());
        $this->season->update(['status' => GameSeason::REVERTED, 'reverted_at' => now()]);
        $this->acts(5, '2026-11-11');

        $this->path()->awardPending(auth()->id());

        $this->assertSame(1, GameSeasonNode::count());
        $state = $this->state();
        $this->assertTrue($state['frozen']);
        $this->assertTrue($state['nodes'][0]['reached']);
    }

    public function test_texts_fit_and_have_no_penalty_words(): void
    {
        $texts = ['Camino de la temporada', '26 de 80 puntos', 'Cada actividad suma 1 (hasta 5 al día); cada misión cumplida, 3.',
            'Siguiente: parada 4 · +15 xp al equipo', 'Parada 3 · 1 ball, alcanzada', 'Parada 7 · 2 balls, faltan 54 puntos',
            '¡Parada 7 del camino!', 'Temporada terminada. Lo ganado queda contigo.', 'Haz tu primera actividad para avanzar.'];
        foreach ($texts as $text) {
            $this->assertLessThanOrEqual(90, mb_strlen($text), $text);
            $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
        }
    }
}
