<?php

namespace Tests\Feature\Game;

use App\Livewire\Home\Panel\FocusTimer;
use App\Livewire\Pomodoro\PomodoroTimer;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\MissionProposalValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-059: compañero de foco junto al reloj del temporizador. */
class FocusCompanionTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    private function companion(string $name, array $types, int $level): void
    {
        $species = GameSpecies::where('name', $name)->first();
        $species->update(['types' => $types]);
        GameCompanion::create(['line_key' => $species->line_key, 'species_id' => $species->id, 'level' => $level, 'first_met_at' => now()]);
    }

    public function test_pomodoro_page_and_focus_dialog_show_the_pomodoro_guardian(): void
    {
        $this->companion('Rocon', ['Roca'], 30);
        $this->companion('Chispin', ['Eléctrico'], 4);

        foreach ([PomodoroTimer::class => 'focus-companion--dial', FocusTimer::class => 'focus-companion--wall'] as $component => $variant) {
            Livewire::test($component)
                ->assertSet('focusCompanion.name', 'Chispin')
                ->assertSeeHtml($variant)
                ->assertSeeHtml('focusCompanion(')
                ->assertSee('Chispin · Nv 4');
        }
    }

    public function test_focus_dialog_keeps_the_task_column_and_companion_stays_with_the_clock(): void
    {
        $this->companion('Chispin', ['Eléctrico'], 4);
        $html = Livewire::test(FocusTimer::class, ['taskHost' => true])->html();

        $this->assertStringContainsString("'has-task': taskId", $html);
        $this->assertStringContainsString('wall-focus-task', $html);
        // El compañero vive dentro de la columna del reloj (.wall-focus), antes de la columna de la tarea.
        $focus = strpos($html, 'class="wall-focus"');
        $this->assertTrue($focus < strpos($html, 'focus-companion--wall') && strpos($html, 'focus-companion--wall') < strpos($html, 'wall-focus-task'));
    }

    public function test_cry_comes_from_the_species_name(): void
    {
        foreach (['Pikachu' => '¡Pika-pika!', 'Ralts' => '¡Ral-ral!', 'Charmander' => '¡Char-char!', 'Gengar' => '¡Gen-gen!', 'Abra' => '¡Abra-abra!'] as $name => $cry) {
            $this->assertSame($cry, \App\Support\Game\SpeciesCry::for($name));
        }
    }

    public function test_focus_dialog_takes_the_guardian_theme_only_with_game_on(): void
    {
        $this->companion('Chispin', ['Eléctrico'], 4);
        Livewire::test(FocusTimer::class)->assertSeeHtml('focus-themed game-stage--electrico')->assertSet('focusCompanion.cry', '¡Chis-chis!');

        $this->gameSettings(['enabled' => false]);
        Livewire::test(FocusTimer::class)->assertDontSeeHtml('focus-themed');
    }

    public function test_hidden_without_companions_or_with_game_off(): void
    {
        Livewire::test(PomodoroTimer::class)->assertSet('focusCompanion', null)->assertDontSeeHtml('focus-companion__art');

        $this->companion('Chispin', ['Eléctrico'], 4);
        $this->gameSettings(['enabled' => false]);
        Livewire::test(FocusTimer::class)->assertSet('focusCompanion', null)->assertDontSeeHtml('focus-companion__art');
    }

    public function test_lines_are_short_warm_and_cover_every_stage(): void
    {
        $lines = config('game.focus_companion');
        $this->assertSame(['ready', 'start', 'half', 'final', 'paused', 'done', 'break'], array_keys($lines));
        foreach ($lines as $stage => $options) {
            $this->assertNotEmpty($options, $stage);
            foreach ($options as $line) {
                $this->assertLessThanOrEqual(60, mb_strlen($line), $line);
                $this->assertNull(MissionProposalValidator::penaltyWord($line), $line);
            }
        }
    }
}
