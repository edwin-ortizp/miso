<?php

namespace Tests\Feature\Game;

use App\Http\Middleware\AddGameEventHeader;
use App\Livewire\Game\GameNotifier;
use App\Models\Game\GameEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-041: la respuesta avisa al navegador solo cuando hubo novedad del juego. */
class GameEventHeaderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        Route::middleware('web')->get('/_test/game/own', function () {
            $this->event($this->user->id, 'own');

            return 'ok';
        });
        Route::middleware('web')->get('/_test/game/other', function () {
            $this->event(User::factory()->create()->id, 'other');

            return 'ok';
        });
        Route::middleware('web')->get('/_test/game/none', fn () => 'ok');
    }

    private function event(int $userId, string $key): GameEvent
    {
        return GameEvent::withoutGlobalScopes()->create([
            'user_id' => $userId, 'source_type' => 'test', 'source_id' => 1, 'source_label' => 'Prueba',
            'kind' => GameEvent::KIND_ACTIVITY, 'effect' => [], 'idempotency_key' => "test:{$key}:".uniqid(), 'occurred_at' => now(),
        ]);
    }

    public function test_marks_the_response_when_the_user_got_a_game_event(): void
    {
        $this->actingAs($this->user)->get('/_test/game/own')->assertHeader(AddGameEventHeader::HEADER, '1');
    }

    public function test_does_not_mark_responses_without_game_events(): void
    {
        $this->actingAs($this->user)->get('/_test/game/none')->assertHeaderMissing(AddGameEventHeader::HEADER);
    }

    public function test_events_of_another_user_do_not_mark_the_response(): void
    {
        $this->actingAs($this->user)->get('/_test/game/other')->assertHeaderMissing(AddGameEventHeader::HEADER);
    }

    public function test_notifier_starts_from_the_last_event_of_the_user(): void
    {
        $own = $this->event($this->user->id, 'mine');
        $this->event(User::factory()->create()->id, 'theirs');

        $this->actingAs($this->user);
        Livewire::test(GameNotifier::class)->assertSet('lastSeenEventId', $own->id);
    }
}
