<?php

namespace Tests\Feature\Game;

use App\Livewire\Notifications\NotificationBell;
use App\Models\Game\GameEncounter;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Game\BallNotice;
use App\Services\Game\EncounterOpener;
use App\Services\Notifications\NotificationPresentation;
use App\Services\Notifications\Push\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BallNoticeTest extends TestCase
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

    private function notices()
    {
        return UserNotification::withoutGlobalScopes()->where('type', BallNotice::TYPE)->get();
    }

    public function test_a_ball_creates_exactly_one_notice(): void
    {
        $this->completeTask('Pagar arriendo');

        $this->assertCount(1, $this->notices());
        $notice = $this->notices()->first();
        $this->assertSame('¡Ganaste una ball!', $notice->title);
        $this->assertSame('Pagar arriendo', $notice->body);
        $this->assertSame(route('game.balls'), $notice->destination());
        $this->assertNull($notice->read_at);
    }

    public function test_granting_again_does_not_duplicate(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        app(BallNotice::class)->granted($encounter, $encounter->event()->withoutGlobalScopes()->first());

        $this->assertCount(1, $this->notices());
    }

    public function test_no_ball_means_no_notice(): void
    {
        $this->gameSettings(['enabled' => false]);
        $this->completeTask();

        $this->assertCount(0, $this->notices());
    }

    public function test_opening_the_ball_marks_the_notice_read(): void
    {
        $this->completeTask();
        app(EncounterOpener::class)->open(GameEncounter::first()->id, auth()->id());

        $this->assertNotNull($this->notices()->first()->read_at);
    }

    public function test_bell_lists_filters_and_presents_it(): void
    {
        $this->completeTask('Pagar arriendo');
        $notice = $this->notices()->first();
        $row = app(NotificationPresentation::class)->forNotices(collect([$notice]), auth()->id())[$notice->id];

        $this->assertSame('bi-gift', $row['icon']);
        $this->assertSame('Ball', $row['context']);
        $this->assertSame(route('game.balls'), $row['destination']);

        Livewire::test(NotificationBell::class)->set('open', true)
            ->set('draftType', 'game_ball')->call('applyFilters')
            ->assertSee('¡Ganaste una ball!')->assertSee('Pagar arriendo');
    }

    public function test_push_dispatcher_never_sends_it(): void
    {
        $this->completeTask();
        $result = app(NotificationDispatcher::class)->run(true);

        $this->assertSame(0, $result['sent']);
        $this->assertNull($this->notices()->first()->push_sent_at);
    }
}
