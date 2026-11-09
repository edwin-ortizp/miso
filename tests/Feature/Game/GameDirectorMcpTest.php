<?php

namespace Tests\Feature\Game;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Game\GetGameDirectorContextTool;
use App\Mcp\Tools\Game\ProposeGameSeasonTool;
use App\Models\Game\GameSeason;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDirectorMcpTest extends TestCase
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
        TaskCategory::create(['name' => 'Salud', 'key' => 'salud']);
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_context_is_aggregated_and_has_no_personal_text(): void
    {
        $this->completeTask('Cita con el psiquiatra');

        LifeTrackerServer::actingAs($this->user)->tool(GetGameDirectorContextTool::class, [])
            ->assertOk()
            ->assertSee('"proposal_due": true')
            ->assertSee('"activities": 1')
            ->assertSee('"limits"')
            ->assertDontSee('psiquiatra');
    }

    public function test_valid_proposal_activates_through_mcp(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(ProposeGameSeasonTool::class, ['season' => SeasonValidatorTest::validProposal()])
            ->assertOk()
            ->assertSee('"status":"active"');

        $this->assertSame(GameSeason::ACTIVE, GameSeason::first()->status);
    }

    public function test_invalid_proposal_returns_an_error_with_reasons(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(ProposeGameSeasonTool::class, ['season' => ['team_bonus' => ['salud' => 3]] + SeasonValidatorTest::validProposal()])
            ->assertHasErrors()
            ->assertSee('rejected');

        $this->assertSame(GameSeason::REJECTED, GameSeason::first()->status);
    }

    public function test_tools_are_isolated_per_user(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(ProposeGameSeasonTool::class, ['season' => SeasonValidatorTest::validProposal()])->assertOk();
        $other = User::factory()->create();

        LifeTrackerServer::actingAs($other)->tool(GetGameDirectorContextTool::class, [])
            ->assertOk()
            ->assertSee('"active_season": null')
            ->assertDontSee('Temporada del Bosque');
        $this->assertSame(1, GameSeason::withoutGlobalScopes()->count());
    }
}
