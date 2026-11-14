<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameCollection;
use App\Livewire\Game\GameSettingsPage;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\Albums;
use App\Services\Game\CompanionProgress;
use App\Services\Game\EncounterOpener;
use App\Services\Game\EncounterRoller;
use App\Services\Game\GameSettings;
use App\Services\Game\RewardGranter;
use App\Services\Game\SpeciesCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GenerationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
        config(['game.mission_types' => []]);
    }

    private function species(int $number, int $generation = 1, array $extra = []): GameSpecies
    {
        return GameSpecies::create(array_replace(['number' => $number, 'generation' => $generation,
            'name' => 'Species '.$number, 'line_key' => 'line-'.$number, 'stage' => 1,
            'rarity' => 'common', 'active' => true, 'types' => ['agua']], $extra));
    }

    private function companion(GameSpecies $s, int $level = 30): GameCompanion
    {
        $c = GameCompanion::create(['user_id' => $this->owner->id, 'line_key' => $s->line_key,
            'species_id' => $s->id, 'xp' => 1200, 'level' => $level, 'first_met_at' => now()]);
        app(CompanionProgress::class)->discover($this->owner->id, $s->id);

        return $c;
    }

    private function ball(GameSpecies $s): GameEncounter
    {
        $e = GameEvent::create(['user_id' => $this->owner->id, 'kind' => 'activity', 'idempotency_key' => 'test-'.uniqid(), 'occurred_at' => now()]);

        return GameEncounter::create(['user_id' => $this->owner->id, 'game_event_id' => $e->id,
            'species_id' => $s->id, 'seed' => 1, 'granted_at' => now()]);
    }

    public function test_default_and_explicit_user_selection_work_without_authentication(): void
    {
        $first = $this->species(1);
        $second = $this->species(152, 2);
        $other = User::factory()->create();
        app(GameSettings::class)->save($other->id, ['enabled_generations' => [2]]);
        auth()->forgetGuards();
        $catalog = app(SpeciesCatalog::class);
        $this->assertSame([$first->id], $catalog->available($this->owner->id)->pluck('id')->all());
        $this->assertSame([$second->id], $catalog->available($other->id)->pluck('id')->all());
        $this->assertSame(2, GameSpecies::where('active', true)->count());
    }

    public function test_pikachu_is_an_entry_without_pichu_and_nonconsecutive_selection_works(): void
    {
        $baby = $this->species(172, 2, ['line_key' => 'pika']);
        $pika = $this->species(25, 1, ['line_key' => 'pika', 'stage' => 2, 'evolves_from_number' => 172]);
        $rai = $this->species(26, 1, ['line_key' => 'pika', 'stage' => 3, 'evolves_from_number' => 25]);
        $this->assertSame([$pika->id], app(SpeciesCatalog::class)->encounters($this->owner->id)->pluck('id')->all());
        $c = $this->companion($pika);
        $this->assertSame([$rai->id], app(CompanionProgress::class)->options($c)->pluck('id')->all());
        app(GameSettings::class)->save($this->owner->id, ['enabled_generations' => [2, 6]]);
        $this->assertSame([$baby->id], app(SpeciesCatalog::class)->encounters($this->owner->id)->pluck('id')->all());
        $this->assertCount(0, app(CompanionProgress::class)->options($c));
    }

    public function test_alternative_branches_require_parent_and_level_and_preserve_current_form(): void
    {
        $base = $this->species(133, 1, ['line_key' => 'eevee']);
        $water = $this->species(134, 1, ['line_key' => 'eevee', 'stage' => 2, 'evolves_from_number' => 133, 'evolve_level' => 10]);
        $electric = $this->species(135, 1, ['line_key' => 'eevee', 'stage' => 2, 'evolves_from_number' => 133, 'evolve_level' => 10]);
        $c = $this->companion($water, 9);
        $catalog = app(SpeciesCatalog::class);
        $this->assertFalse($catalog->encounters($this->owner->id)->contains('id', $electric->id));
        app(CompanionProgress::class)->discover($this->owner->id, $base->id);
        $this->assertFalse($catalog->encounters($this->owner->id)->contains('id', $electric->id));
        $c->update(['level' => 30]);
        $this->assertTrue($catalog->encounters($this->owner->id)->contains('id', $electric->id));
        $ball = $this->ball($electric);
        $result = app(EncounterOpener::class)->open($ball->id, $this->owner->id);
        $this->assertTrue($result['is_new_species']);
        $this->assertFalse($result['is_new']);
        $this->assertSame($water->id, $c->fresh()->species_id);
        $this->assertGreaterThan(1200, $c->fresh()->xp);
        $this->assertSame($result, app(EncounterOpener::class)->open($ball->id, $this->owner->id));
        $this->assertSame(1, GameCompanion::count());
    }

    public function test_pending_ball_remains_openable_after_disabling_its_generation(): void
    {
        $s = $this->species(152, 2);
        $ball = $this->ball($s);
        $this->assertSame($s->id, app(EncounterOpener::class)->open($ball->id, $this->owner->id)['species_id']);
        $this->get(route('game.collection'))->assertOk()->assertSee('Generación en pausa');
    }

    public function test_generation_selection_validation_and_section_isolation(): void
    {
        $this->species(1);
        $this->species(152, 2);
        $settings = app(GameSettings::class);
        Livewire::test(GameSettingsPage::class, ['section' => 'generations'])
            ->set('enabledGenerations', [2])->set('sounds', false)->call('save')->assertHasNoErrors();
        $this->assertSame([2], $settings->for($this->owner->id)['enabled_generations']);
        $this->assertTrue($settings->for($this->owner->id)['sounds']);
        Livewire::test(GameSettingsPage::class, ['section' => 'generations'])
            ->set('enabledGenerations', [])->call('save')->assertHasErrors('enabledGenerations');
        Livewire::test(GameSettingsPage::class, ['section' => 'generations'])
            ->set('enabledGenerations', [10])->call('save')->assertHasErrors('enabledGenerations.0');
        $this->assertSame([2], $settings->for($this->owner->id)['enabled_generations']);
        foreach (['game.settings', 'game.settings.generations', 'game.settings.rewards', 'game.settings.companion', 'game.settings.celebrations'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Guardar cambios')->assertSee('x-dirty-guard', false);
        }
    }

    public function test_settings_reject_another_users_favorite(): void
    {
        $s = $this->species(1);
        $other = User::factory()->create();
        $c = GameCompanion::withoutGlobalScopes()->create(['user_id' => $other->id, 'line_key' => $s->line_key, 'species_id' => $s->id, 'first_met_at' => now()]);
        Livewire::test(GameSettingsPage::class, ['section' => 'companion'])->set('favoriteCompanionId', $c->id)
            ->call('save')->assertHasErrors('favoriteCompanionId');
    }

    public function test_recommendation_requires_every_species_and_dismissal_is_persistent(): void
    {
        $base = $this->species(1);
        $this->species(2, 1, ['line_key' => $base->line_key, 'stage' => 2]);
        $this->species(152, 2);
        $catalog = app(SpeciesCatalog::class);
        $this->companion($base);
        $this->assertNull($catalog->recommendation($this->owner->id));
        app(CompanionProgress::class)->discover($this->owner->id, GameSpecies::where('number', 2)->value('id'));
        $this->assertSame(2, $catalog->recommendation($this->owner->id)['next']['generation']);
        Livewire::test(GameCollection::class)->assertSee('Elegir generación')->call('dismissGenerationRecommendation')->assertDontSee('Elegir generación');
        $this->assertNull($catalog->recommendation($this->owner->id));
        $this->assertSame([1], app(GameSettings::class)->for($this->owner->id)['enabled_generations']);
    }

    public function test_no_eligible_species_means_no_ball_and_season_cannot_bypass_selection(): void
    {
        $this->species(152, 2);
        $this->assertNull(app(RewardGranter::class)->grantMissionBall($this->owner->id, 'empty', 'Prueba'));
        $this->assertSame('empty_catalog', GameEvent::where('idempotency_key', 'empty')->first()->effect['reason']);
        $first = $this->species(1);
        $ball = app(RewardGranter::class)->grantMissionBall($this->owner->id, 'eligible', 'Prueba');
        $this->assertSame($first->id, $ball->species_id);
    }

    public function test_novelty_protection_finds_an_unknown_species_in_a_known_family(): void
    {
        $base = $this->species(133, 1, ['line_key' => 'eevee']);
        $branch = $this->species(135, 1, ['line_key' => 'eevee', 'stage' => 2]);
        $picked = app(EncounterRoller::class)->roll(collect([$base, $branch]), ['eevee'], ['common' => 1], true, 5, knownSpeciesIds: [$base->id]);
        $this->assertSame($branch->id, $picked->id);
    }

    public function test_real_catalog_is_completable_with_first_generation_and_nonconsecutive_selection(): void
    {
        $this->artisan('game:import-species', ['file' => base_path('pokemon.json')])->assertSuccessful();
        $catalog = app(SpeciesCatalog::class);
        foreach ([[1], [2], [1, 6]] as $selection) {
            $user = User::factory()->create();
            app(GameSettings::class)->save($user->id, ['enabled_generations' => $selection]);
            for ($round = 0; $round < 10; $round++) {
                $known = $catalog->discoveredIds($user->id);
                $next = $catalog->encounters($user->id)->whereNotIn('id', $known);
                if ($next->isEmpty()) {
                    break;
                }
                foreach ($next as $species) {
                    GameCompanion::withoutEvents(fn () => GameCompanion::withoutGlobalScopes()->firstOrCreate(
                        ['user_id' => $user->id, 'line_key' => $species->line_key],
                        ['species_id' => $species->id, 'xp' => 4000, 'level' => 100, 'first_met_at' => now()],
                    ));
                    GameDiscovery::withoutEvents(fn () => GameDiscovery::withoutGlobalScopes()->firstOrCreate(
                        ['user_id' => $user->id, 'species_id' => $species->id], ['discovered_at' => now()],
                    ));
                }
            }
            $missing = $catalog->available($user->id)->whereNotIn('id', $catalog->discoveredIds($user->id))->pluck('name')->all();
            $this->assertSame([], $missing, 'Unreachable species for '.json_encode($selection));
        }
    }

    public function test_changing_generations_never_repeats_album_rewards(): void
    {
        $first = $this->species(1);
        $second = $this->species(152, 2);
        $this->companion($first);
        $albums = app(Albums::class);
        $albums->awardIfComplete($this->owner->id, $first->id);
        $this->assertSame(1, DB::table('game_album_awards')->count());
        app(GameSettings::class)->save($this->owner->id, ['enabled_generations' => [2]]);
        $this->assertSame(0, $albums->overview($this->owner->id)[0]['found']);
        $this->assertSame(1, DB::table('game_album_awards')->count());
        app(CompanionProgress::class)->discover($this->owner->id, $second->id);
        $albums->awardIfComplete($this->owner->id, $second->id);
        $this->assertSame(1, DB::table('game_album_awards')->count());
    }
}
