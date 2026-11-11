<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GuardianCard;
use App\Livewire\Health\HealthIndex;
use App\Livewire\Task\TaskList;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\HealthEvent;
use App\Models\User;
use App\Services\Game\MissionProposalValidator;
use App\Services\Game\ModuleGuardian;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-055: guardianes de módulo en el panel lateral de Tareas y Salud. */
class ModuleGuardianTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function companion(string $name, array $types, int $level = 1): GameCompanion
    {
        $species = GameSpecies::where('name', $name)->first();
        $species->update(['types' => $types]);

        return GameCompanion::create(['line_key' => $species->line_key, 'species_id' => $species->id, 'level' => $level, 'first_met_at' => now()]);
    }

    private function service(): ModuleGuardian
    {
        return app(ModuleGuardian::class);
    }

    public function test_picks_highest_level_companion_with_module_affinity(): void
    {
        $this->companion('Rocon', ['Roca'], 30);
        $this->companion('Chispin', ['Psíquico'], 5);
        $this->companion('Gotin', ['Acero'], 9);

        $this->assertSame('Gotin', $this->service()->pick($this->user->id, 'tasks')['name']);
    }

    public function test_ties_go_to_the_oldest_companion(): void
    {
        $this->companion('Chispin', ['Psíquico'], 5);
        Carbon::setTestNow(now()->addHour());
        $this->companion('Gotin', ['Acero'], 5);

        $this->assertSame('Chispin', $this->service()->pick($this->user->id, 'tasks')['name']);
    }

    public function test_falls_back_to_highest_level_without_affinity(): void
    {
        $this->companion('Rocon', ['Roca'], 30);
        $this->companion('Gotin', ['Agua'], 2);

        $this->assertSame('Rocon', $this->service()->pick($this->user->id, 'health')['name']);
    }

    public function test_null_without_companions_or_with_game_off(): void
    {
        $this->assertNull($this->service()->pick($this->user->id, 'tasks'));

        $this->companion('Gotin', ['Hada']);
        $this->gameSettings(['enabled' => false]);
        $this->assertNull($this->service()->pick($this->user->id, 'health'));
    }

    public function test_line_follows_thresholds_and_care(): void
    {
        $morning = now();
        $evening = now()->setTime(18, 0);

        $this->assertSame('Día libre, disfrútalo.', ModuleGuardian::line('tasks', 0, 0, false, $morning));
        $this->assertSame('¡Vas volando hoy!', ModuleGuardian::line('tasks', 7, 10, false, $morning));
        $this->assertSame('Buen ritmo, sigue así.', ModuleGuardian::line('tasks', 3, 10, false, $morning));
        $this->assertSame('Hoy empezamos juntos.', ModuleGuardian::line('tasks', 1, 10, false, $morning));
        $this->assertSame('Un pasito y ya cuenta.', ModuleGuardian::line('tasks', 1, 10, false, $evening));
        $this->assertSame('Recupérate con calma, aquí estoy.', ModuleGuardian::line('health', 9, 10, true, $morning));
    }

    public function test_lines_are_short_and_kind(): void
    {
        foreach (config('game.guardian_lines') as $lines) {
            foreach ($lines as $text) {
                $this->assertLessThanOrEqual(60, mb_strlen($text), $text);
                $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
            }
        }
    }

    public function test_tasks_page_shows_guardian_card_and_refresh_keeps_companion(): void
    {
        $companion = $this->companion('Chispin', ['Psíquico'], 4);

        Livewire::test(TaskList::class)->assertSeeLivewire(GuardianCard::class);

        $card = Livewire::test(GuardianCard::class, ['module' => 'tasks'])
            ->assertSee('Ver ficha de Chispin')
            ->assertSee('/game/collection?species='.$companion->species_id, false)
            ->assertSeeHtml('game-stage--')->assertSee('Chispin · Nv 4')
            ->assertSeeHtml('wire:poll.300s.visible="refreshAdvice"')
            ->assertSee('Otro consejo')
            ->assertSee('Día libre, disfrútalo.');
        $before = $card->get('guardian');

        $this->companion('Gotin', ['Psíquico'], 50);
        \App\Models\Task::create(['title' => 'Llamar al banco', 'start_date' => today()]);
        $this->completeTask('Pagar arriendo');
        $card->call('refreshAdvice');

        $this->assertSame($before, $card->get('guardian'));
        $card->assertDontSee('Día libre, disfrútalo.');
    }

    public function test_guardian_hidden_with_game_off(): void
    {
        $this->companion('Chispin', ['Psíquico']);
        $this->gameSettings(['enabled' => false]);

        Livewire::test(GuardianCard::class, ['module' => 'tasks'])->assertDontSeeHtml('game-guardian');
    }

    public function test_health_guardian_cares_when_an_event_is_in_progress(): void
    {
        $this->companion('Gotin', ['Hada']);

        Livewire::test(HealthIndex::class)->assertSeeLivewire(GuardianCard::class);
        Livewire::test(GuardianCard::class, ['module' => 'health'])
            ->assertSee('Ver ficha de Gotin')
            ->assertSee('Todo en orden, cuídate.');
    }

    public function test_new_modules_pick_by_affinity_and_mount_the_card(): void
    {
        $this->companion('Brotelo', ['Planta'], 3);
        $this->companion('Gotin', ['Hielo'], 2);
        $this->companion('Rocon', ['Roca'], 4);
        $this->companion('Chispin', ['Eléctrico'], 1);

        foreach (['habits' => 'Brotelo', 'water' => 'Gotin', 'exercise' => 'Rocon', 'pomodoro' => 'Chispin'] as $module => $name) {
            $this->assertSame($name, $this->service()->pick($this->user->id, $module)['name'], $module);
        }
        foreach ([\App\Livewire\Water\WaterDaily::class, \App\Livewire\Exercise\ExerciseDaily::class, \App\Livewire\Pomodoro\PomodoroTimer::class, \App\Livewire\Habit\HabitTracker::class] as $page) {
            Livewire::test($page)->assertSeeLivewire(GuardianCard::class);
        }
    }

    public function test_habits_replaces_coach_card_only_with_a_guardian(): void
    {
        \App\Models\HabitDefinition::create(['name' => 'Caminar', 'time_of_day' => 'morning']);
        Livewire::test(\App\Livewire\Habit\HabitTracker::class)
            ->assertSeeHtml('habit-coach-card')->assertDontSeeLivewire(GuardianCard::class);

        $this->companion('Brotelo', ['Planta']);
        Livewire::test(\App\Livewire\Habit\HabitTracker::class)
            ->assertDontSeeHtml('habit-coach-card')->assertSeeLivewire(GuardianCard::class);
    }

    public function test_coach_advice_brings_its_action(): void
    {
        $habit = \App\Models\HabitDefinition::create(['name' => 'Caminar', 'time_of_day' => 'morning']);
        $coach = collect(app(\App\Services\Game\Guardian\HabitAdvice::class)->candidates(now()))->firstWhere('key', 'coach');

        $this->assertSame(['label' => 'Ir al hábito sugerido', 'target' => 'habit-item-'.$habit->id], $coach['action']);
    }

    public function test_guardian_sheet_opens_in_a_modal_without_leaving_the_module(): void
    {
        $companion = $this->companion('Gotin', ['Agua'], 3);
        $card = Livewire::test(GuardianCard::class, ['module' => 'water']);

        $this->assertNull($card->viewData('sheet'), 'la ficha no se arma antes de abrir');
        $card->assertDontSeeHtml('href="http://localhost/game/collection?species='.$companion->species_id.'" wire:navigate
               class="game-guardian__art"');

        $card->call('openSheet')
            ->assertNoRedirect()
            ->assertSet('showSheet', true)
            ->assertSee('Ver colección')
            ->assertSee('Lo conociste el')
            ->assertDontSeeHtml('wire:click="evolve(')
            ->assertDontSeeHtml('wire:click="moreHistory"');
        $this->assertSame($companion->species_id, $card->viewData('sheet')['species']->id);

        $card->call('refreshAdvice');
        $this->assertTrue($card->get('showSheet'));
    }

    public function test_chg063_modules_get_a_rail_only_with_a_guardian(): void
    {
        $pages = ['/goals' => 'goals', '/mood' => 'mood', '/journal' => 'journal', '/negative-habits' => 'negative-habits'];
        foreach ($pages as $url => $module) {
            $this->get($url)->assertOk()->assertDontSee('Tu guardián');
        }

        $this->companion('Rocon', ['Dragón'], 5);
        $this->companion('Gotin', ['Fantasma'], 4);
        $this->companion('Brotelo', ['Siniestro'], 3);
        $this->companion('Chispin', ['Hada'], 2);
        foreach (['goals' => 'Rocon', 'journal' => 'Gotin', 'negative-habits' => 'Brotelo', 'mood' => 'Chispin'] as $module => $name) {
            $this->assertSame($name, $this->service()->pick($this->user->id, $module)['name'], $module);
        }
        foreach ($pages as $url => $module) {
            $this->get($url)->assertOk()->assertSee('Tu guardián')->assertSee('md-module-workspace--rail', false);
        }
    }

    public function test_chg064_modules_show_the_guardian(): void
    {
        $this->get('/meals/weekly')->assertOk()->assertDontSee('Tu guardián');
        $this->get('/vehicles')->assertOk()->assertDontSee('Tu guardián');

        $this->companion('Brotelo', ['Bicho'], 5);
        $this->companion('Chispin', ['Acero'], 4);
        $this->companion('Rocon', ['Tierra'], 3);
        $this->companion('Gotin', ['Normal'], 2);
        foreach (['meals' => 'Brotelo', 'vehicles' => 'Chispin', 'plans' => 'Rocon', 'relationships' => 'Gotin'] as $module => $name) {
            $this->assertSame($name, $this->service()->pick($this->user->id, $module)['name'], $module);
        }
        foreach (['/meals/weekly', '/vehicles'] as $url) {
            $this->get($url)->assertOk()->assertSee('Tu guardián')->assertSee('md-module-workspace--rail', false);
        }
        foreach ([\App\Livewire\Relationship\RelationshipIndex::class, \App\Livewire\Plan\PlanIndex::class] as $page) {
            Livewire::test($page)->assertSeeLivewire(GuardianCard::class);
        }
    }
}
