<?php

namespace Tests\Feature\Game;

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

    public function test_tasks_panel_shows_guardian_and_line_updates_without_changing_it(): void
    {
        $companion = $this->companion('Chispin', ['Psíquico'], 4);

        $list = Livewire::test(TaskList::class)
            ->assertSee('Ver ficha de Chispin')
            ->assertSee('/game/collection?species='.$companion->species_id, false)
            ->assertSeeHtml('game-stage--')->assertSee('Chispin · Nv 4')
            ->assertSee('Día libre, disfrútalo.');
        $before = $list->get('guardian');

        $this->companion('Gotin', ['Psíquico'], 50);
        \App\Models\Task::create(['title' => 'Llamar al banco', 'start_date' => today()]);
        $this->completeTask('Pagar arriendo');
        $list->dispatch('task-records-changed');

        $this->assertSame($before, $list->get('guardian'));
        $list->assertDontSee('Día libre, disfrútalo.');
    }

    public function test_tasks_panel_hidden_with_game_off(): void
    {
        $this->companion('Chispin', ['Psíquico']);
        $this->gameSettings(['enabled' => false]);

        Livewire::test(TaskList::class)->assertDontSeeHtml('game-guardian');
    }

    public function test_health_guardian_cares_when_an_event_is_in_progress(): void
    {
        $this->companion('Gotin', ['Hada']);

        Livewire::test(HealthIndex::class)
            ->assertSee('Ver ficha de Gotin')
            ->assertSee('Todo en orden, cuídate.');

        HealthEvent::create(['type' => 'illness', 'title' => 'Gripa', 'event_date' => today()]);
        Livewire::test(HealthIndex::class)->assertSee('Recupérate con calma, aquí estoy.');
    }
}
