<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\GameRules;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeekPlannerTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota')); // miércoles
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->withWeeklyMissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function companions(int $count): void
    {
        $species = GameSpecies::first();
        foreach (range(1, $count) as $i) {
            GameCompanion::create(['line_key' => "linea-{$i}", 'species_id' => $species->id, 'first_met_at' => now()]);
        }
    }

    public function test_team_has_up_to_six_and_is_stable_within_the_week(): void
    {
        $this->companions(8);
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        $planner = app(WeekPlanner::class);

        $week = $planner->forWeek(auth()->id(), now());
        $first = $week->members()->pluck('companion_id')->all();
        $again = $planner->forWeek(auth()->id(), now()->addDays(3)); // domingo, misma semana

        $this->assertSame($week->id, $again->id);
        $this->assertCount(6, $first);
        $this->assertSame($first, $again->members()->pluck('companion_id')->all());
        $this->assertSame('2026-11-09', $week->week_start->toDateString());
        $this->assertTrue(GameTeamMember::all()->every(fn ($m) => in_array($m->ability_key, ['hogar', 'habits', 'focus', 'goals', 'health'], true)));
    }

    public function test_new_week_creates_a_new_team_and_keeps_the_previous_one(): void
    {
        $this->companions(8);
        $planner = app(WeekPlanner::class);
        $planner->forWeek(auth()->id(), now());

        $next = $planner->forWeek(auth()->id(), now()->addWeek());

        $this->assertSame(2, GameWeek::count());
        $this->assertSame(12, GameTeamMember::count());
        $this->assertSame('2026-11-16', $next->week_start->toDateString());
    }

    public function test_rotation_favours_companions_used_less(): void
    {
        $this->companions(7);
        $planner = app(WeekPlanner::class);
        $counts = array_fill_keys(GameCompanion::pluck('id')->all(), 0);

        foreach (range(0, 11) as $offset) {
            $week = $planner->forWeek(auth()->id(), now()->addWeeks($offset));
            foreach ($week->members()->pluck('companion_id') as $id) {
                $counts[$id]++;
            }
        }

        // 12 semanas x 6 lugares entre 7: la rotación reparte (nadie queda fuera ni acapara).
        $this->assertGreaterThanOrEqual(7, min($counts));
        $this->assertLessThanOrEqual(12, max($counts));
    }

    public function test_team_fills_empty_slots_when_new_companions_arrive(): void
    {
        $planner = app(WeekPlanner::class);
        $week = $planner->forWeek(auth()->id(), now());
        $this->assertSame(0, $week->members()->count());

        $this->companions(2);
        $planner->forWeek(auth()->id(), now());

        $this->assertSame(2, $week->members()->count());
    }

    public function test_three_missions_with_modest_targets(): void
    {
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        TaskCategory::create(['name' => 'Trabajo', 'key' => 'trabajo']);
        foreach (range(1, 4) as $i) {
            Task::create(['title' => 'Pendiente '.$i, 'category' => 'hogar']);
        }
        $this->habit('Caminar');

        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $missions = $week->missions()->get();

        $this->assertCount(3, $missions);
        $this->assertEqualsCanonicalizing(
            [GameMission::TYPE_CATEGORY, GameMission::TYPE_HABITS, GameMission::TYPE_VARIETY],
            $missions->pluck('type')->all(),
        );
        $this->assertTrue($missions->every(fn ($m) => $m->target >= 2 && $m->target <= 4));
    }

    public function test_without_categories_or_habits_only_possible_missions_are_created(): void
    {
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());

        $this->assertSame(0, $week->missions()->count());
    }

    public function test_weeks_are_isolated_between_users(): void
    {
        app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, GameWeek::count());
    }

    public function test_categories_rank_real_pending_tasks_and_cap_targets_without_duplicates(): void
    {
        config(['game.mission_types' => ['category_tasks'], 'game.mission_target' => [4, 4]]);
        foreach (['hogar' => 1, 'trabajo' => 3, 'finanzas' => 0] as $category => $count) {
            TaskCategory::create(['name' => ucfirst($category), 'key' => $category]);
            for ($i = 0; $i < $count; $i++) {
                Task::create(['title' => 'Pendiente '.$i, 'category' => $category]);
            }
        }
        foreach (range(1, 8) as $i) {
            Task::create(['title' => 'Recurrente '.$i, 'category' => 'finanzas', 'is_recurrent' => true]);
        }
        foreach ([['completed' => true], ['is_private' => true], ['is_recurrence_history' => true]] as $excluded) {
            Task::create(['title' => 'Excluida', 'category' => 'finanzas'] + $excluded);
        }
        $other = User::factory()->create();
        TaskCategory::forceCreate(['user_id' => $other->id, 'name' => 'Finanzas', 'key' => 'finanzas']);
        Task::forceCreate(['user_id' => $other->id, 'title' => 'Ajena', 'category' => 'finanzas']);
        $missions = app(WeekPlanner::class)->forWeek(auth()->id(), now())->missions()->orderBy('id')->get();
        $this->assertSame(['trabajo', 'hogar'], $missions->map(fn ($m) => $m->params['category'])->all());
        $this->assertSame([3, 1], $missions->pluck('target')->all());
    }

    public function test_focus_only_breaks_count_ties_and_seed_is_reproducible(): void
    {
        foreach (['hogar' => 1, 'trabajo' => 2, 'salud' => 1, 'deporte' => 1] as $key => $count) {
            TaskCategory::create(['name' => $key, 'key' => $key]);
            for ($i = 0; $i < $count; $i++) {
                Task::create(['title' => 'Pendiente '.$i, 'category' => $key]);
            }
        }
        $this->mock(GameRules::class, function ($mock) {
            $mock->shouldReceive('for')->andReturn(['mission_types' => ['category_tasks'], 'mission_target' => [2, 4], 'mission_focus' => ['salud']]);
        });
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $categories = $week->missions()->orderBy('id')->get()->map(fn ($m) => $m->params['category'])->all();
        $this->assertSame(['trabajo', 'salud'], array_slice($categories, 0, 2));
        $this->assertContains($categories[2], ['hogar', 'deporte']);

        // Recrear solo en la base desechable con la misma semilla reproduce desempates y metas.
        $before = $week->missions()->orderBy('id')->get(['type', 'title', 'params', 'target'])->toArray();
        $week->missions()->delete();
        $method = new \ReflectionMethod(WeekPlanner::class, 'createMissions');
        $method->invoke(app(WeekPlanner::class), $week);
        $this->assertSame($before, $week->missions()->orderBy('id')->get(['type', 'title', 'params', 'target'])->toArray());
    }

    public function test_variety_does_not_count_empty_categories(): void
    {
        TaskCategory::create(['name' => 'Vacía', 'key' => 'vacia']);
        $this->habit();
        config(['game.mission_types' => ['variety']]);
        $this->assertSame(0, app(WeekPlanner::class)->forWeek(auth()->id(), now())->missions()->count());
    }
}
