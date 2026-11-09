<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\BallNotifier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BallNotifierTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shows_a_notice_after_a_new_ball(): void
    {
        $notifier = Livewire::test(BallNotifier::class)->assertSet('notice', 0);

        $this->completeTask();
        $notifier->dispatch('task-completed')->assertSet('notice', 1)->assertSee('¡Ganaste una ball!');

        // Sin balls nuevas, no vuelve a avisar.
        $notifier->call('check')->assertSet('notice', 1);
    }

    public function test_no_notice_when_the_daily_limit_blocks_the_ball(): void
    {
        $this->gameSettings(['daily_limit' => 0]);
        $notifier = Livewire::test(BallNotifier::class);

        $this->completeTask();
        $notifier->dispatch('task-completed')->assertSet('notice', 0);
    }

    public function test_notifier_is_rendered_in_the_layout(): void
    {
        $this->get(route('game.balls'))->assertOk()->assertSeeLivewire(BallNotifier::class);
    }
}
