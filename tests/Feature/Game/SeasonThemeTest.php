<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameNotifier;
use App\Livewire\Game\GameSeasonView;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Game\GetGameDirectorContextTool;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\EncounterRoller;
use App\Services\Game\SeasonValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-045: temporadas temáticas por calendario. */
class SeasonThemeTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->user = User::factory()->create(['birth_date' => '1990-11-20']);
        $this->actingAs($this->user);
        $this->seedSpecies();
        GameSpecies::where('line_key', 'chispin')->update(['types' => ['Hielo']]);
        GameSpecies::where('line_key', 'brotelo')->update(['types' => ['Planta']]);
        TaskCategory::create(['name' => 'Salud', 'key' => 'salud']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $extra */
    private function validate(array $extra): array
    {
        return app(SeasonValidator::class)->validate(SeasonValidatorTest::validProposal() + $extra, $this->user->id);
    }

    private function activeSeason(array $config): GameSeason
    {
        return GameSeason::create([
            'user_id' => $this->user->id, 'name' => 'Luces de diciembre', 'narrative' => 'Brilla el invierno.',
            'status' => GameSeason::ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeeks(3),
            'config' => $config, 'proposal' => [],
        ]);
    }

    public function test_validator_accepts_a_theme_and_featured_types(): void
    {
        $result = $this->validate(['theme' => 'navidad', 'featured_types' => ['Hielo']]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('navidad', $result['config']['theme']);
        $this->assertSame(['hielo'], $result['config']['featured_types']);
    }

    public function test_validator_rejects_unknown_theme_too_many_or_missing_types(): void
    {
        $this->assertNotSame([], $this->validate(['theme' => 'pascua'])['errors']);
        $this->assertNotSame([], $this->validate(['featured_types' => ['hielo', 'planta', 'hielo2']])['errors']);
        $this->assertNotSame([], $this->validate(['featured_types' => ['dragon']])['errors']);
    }

    public function test_director_context_lists_upcoming_themes_and_birthday_without_year(): void
    {
        $response = LifeTrackerServer::actingAs($this->user)->tool(GetGameDirectorContextTool::class, []);

        $response->assertOk()
            ->assertSee('"theme": "navidad"')
            ->assertSee('"theme": "cumpleanos"')
            ->assertSee('"starts_on": "11-13"')
            ->assertDontSee('1990')
            ->assertDontSee('"theme": "halloween"');
    }

    public function test_without_birth_date_the_birthday_theme_is_not_suggested(): void
    {
        $this->user->update(['birth_date' => null]);

        LifeTrackerServer::actingAs($this->user)->tool(GetGameDirectorContextTool::class, [])
            ->assertDontSee('"theme": "cumpleanos"');
    }

    public function test_featured_types_are_more_likely_within_their_rarity(): void
    {
        $species = GameSpecies::where('stage', 1)->where('rarity', 'common')->get();
        $roller = app(EncounterRoller::class);
        $count = fn (array $types) => collect(range(1, 400))
            ->filter(fn ($seed) => $roller->roll($species, [], ['common' => 100], false, $seed, null, 1, $types)?->line_key === 'chispin')
            ->count();

        $base = $count([]);
        $featured = $count(['hielo']);

        $this->assertGreaterThan($base + 30, $featured);
        $this->assertSame($count(['hielo']), $featured, 'misma semilla, mismo resultado');
    }

    public function test_season_tab_shows_the_theme_and_featured_types(): void
    {
        $this->activeSeason(['theme' => 'navidad', 'featured_types' => ['hielo']]);

        Livewire::test(GameSeasonView::class)
            ->assertSee('Temporada · Navidad')
            ->assertSee('Tipos destacados')
            ->assertSeeHtml('game-season--theme-navidad');
    }

    public function test_theme_celebration_can_appear_and_respects_the_setting(): void
    {
        $this->activeSeason(['theme' => 'navidad']);
        $this->gameSettings(['daily_limit' => 50, 'celebration_set' => []]);

        $notifier = Livewire::test(GameNotifier::class);
        $this->completeTask('A');
        $notifier->call('check')->assertDispatched('game-celebrate', name: 'snow');

        $this->gameSettings(['celebrations' => false]);
        $notifier = Livewire::test(GameNotifier::class);
        $this->completeTask('B');
        $notifier->call('check')->assertNotDispatched('game-celebrate');
    }
}
