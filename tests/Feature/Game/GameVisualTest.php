<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameCollection;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\CompanionProgress;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameVisualTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameSpecies::where('name', 'Brotelo')->update([
            'types' => json_encode(['planta', 'veneno']),
            'stats' => json_encode(['hp' => 45, 'attack' => 49, 'defense' => 49, 'special_attack' => 65, 'special_defense' => 65, 'speed' => 45]),
            'rarity' => 'rare',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function companion(int $level): GameCompanion
    {
        $species = GameSpecies::where('name', 'Brotelo')->first();
        GameDiscovery::create(['species_id' => $species->id, 'discovered_at' => now()]);

        return GameCompanion::create(['line_key' => 'brotelo', 'species_id' => $species->id, 'xp' => ($level - 1) * 40 + 12, 'level' => $level, 'first_met_at' => now()]);
    }

    public function test_power_formula_grows_with_level_and_has_a_default(): void
    {
        $progress = app(CompanionProgress::class);
        $companion = $this->companion(1);

        $this->assertSame(318, $progress->power($companion));               // suma de estadísticas
        $companion->level = 11;
        $this->assertSame(477, $progress->power($companion));               // 318 × 1,5
        $plain = GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'level' => 1, 'first_met_at' => now()]);
        $this->assertSame(300, $progress->power($plain));                   // sin estadísticas
    }

    public function test_type_class_maps_accents_and_unknown_types(): void
    {
        $this->assertSame('game-type--psiquico', CompanionProgress::typeClass('psíquico'));
        $this->assertSame('game-type--dragon', CompanionProgress::typeClass('Dragón'));
        $this->assertSame('game-type--neutral', CompanionProgress::typeClass('cósmico'));
        $this->assertSame('game-type--neutral', CompanionProgress::typeClass(null));
    }

    public function test_team_card_shows_level_power_xp_types_and_affinity(): void
    {
        $companion = $this->companion(3);
        app(WeekPlanner::class)->forWeek(auth()->id(), now())->members()->first()->update(['ability_label' => 'Hogar']);

        $this->get(route('game.week'))->assertOk()
            ->assertSee('game-collectible md-btn-text game-type--planta game-rarity--rare', false)
            ->assertSee('game-collection-cards', false)
            ->assertSee('ATQ')
            ->assertSee('Nv 3')
            ->assertSee('Poder 350')                                         // 318 × 1,1 = 349,8
            ->assertSee('12 / 40 xp')
            ->assertSee('veneno')
            ->assertSee('Afinidad Hogar')
            ->assertSee('Rara');
    }

    public function test_collection_cards_show_rarity_with_text_and_hidden_without_color(): void
    {
        $this->companion(1);

        Livewire::test(GameCollection::class)
            ->assertSeeHtml('is-discovered game-type--planta game-rarity--rare')
            ->assertSee('Rara')
            ->assertSeeHtml('game-grid__item is-hidden');
    }

    public function test_sheet_shows_hero_power_stat_bars_and_chain_images(): void
    {
        $companion = $this->companion(2);

        Livewire::test(GameCollection::class)
            ->call('showSpecies', $companion->species_id)
            ->assertSeeHtml('game-sheet__hero')
            ->assertSee('Poder 334')                                         // 318 × 1,05
            ->assertSee('Ataque')
            ->assertSeeHtml('--md-progress-value: 19%')                      // 49 / 255
            ->assertSeeHtml('game-chain__item is-discovered')
            ->assertSee('Línea de tiempo');
    }

    public function test_team_cards_are_independent_one_per_member(): void
    {
        config(['game.image_base_url' => 'https://img.test']);
        $this->companion(2);
        $other = GameSpecies::where('name', 'Gotin')->first();
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => $other->id, 'level' => 1, 'first_met_at' => now()]);
        app(WeekPlanner::class)->forWeek(auth()->id(), now());

        $html = $this->get(route('game.week'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="game-collectible '));
        // CHG-036: el arte va dentro de su ventana (se apoya en la base y sobresale solo hacia arriba).
        $this->assertMatchesRegularExpression('/<span class="game-collectible__art">\s*(?:<!--.*?-->\s*)*<img /s', $html);
        $this->assertStringNotContainsString('class="game-team"', $html);
    }

    public function test_new_species_reveal_uses_the_flipping_card_and_repeats_do_not(): void
    {
        $this->completeTask('Primera');
        $encounter = \App\Models\Game\GameEncounter::first();
        $encounter->update(['species_id' => GameSpecies::where('name', 'Brotelo')->value('id')]);

        \Livewire\Livewire::test(\App\Livewire\Game\GameBalls::class)
            ->call('open', $encounter->id)
            ->assertSeeHtml('is-flipping')
            ->assertSeeHtml('game-discovery__card');

        $this->completeTask('Segunda');
        $repeat = \App\Models\Game\GameEncounter::latest('id')->first();
        $repeat->update(['species_id' => GameSpecies::where('name', 'Brotelo')->value('id')]);

        \Livewire\Livewire::test(\App\Livewire\Game\GameBalls::class)
            ->call('open', $repeat->id)
            ->assertDontSeeHtml('is-flipping');
    }
}
