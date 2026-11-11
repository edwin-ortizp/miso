<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameBalls;
use App\Models\User;
use App\Services\Game\DailyChallenge;
use App\Services\Game\Streak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameBallsAdventureTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    public function test_streak_states_keep_guidance_and_distinct_week_symbols(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $base = app(Streak::class)->summary(auth()->id());
        $base['last7'] = collect(['met', 'grace', 'none', 'met', 'none', 'none', 'today'])
            ->map(fn ($status, $i) => ['date' => today()->subDays(6 - $i)->toDateString(), 'status' => $status])->all();
        $variants = [
            [['days' => 0, 'today' => 0, 'met_today' => false, 'broken' => false], 'Hoy empieza tu racha'],
            [['days' => 4, 'today' => 1, 'met_today' => false, 'broken' => false], 'Hoy: 1 de 3 acciones'],
            [['days' => 5, 'today' => 3, 'met_today' => true, 'broken' => false], 'Hoy ya está'],
            [['days' => 0, 'today' => 0, 'met_today' => false, 'broken' => true, 'best' => 8], 'Tu mejor racha fue de 8 días'],
            [['days' => 4, 'today' => 3, 'today_areas' => 1, 'met_today' => false, 'broken' => false], 'Suma otra área'],
        ];
        foreach ($variants as [$changes, $text]) {
            $this->instance(Streak::class, \Mockery::mock(Streak::class)->shouldReceive('summary')->andReturn(array_replace($base, $changes))->getMock());
            Livewire::test(GameBalls::class)->assertSee($text)
                ->assertSeeHtml('aria-label="Cómo funciona la racha"')
                ->assertSeeHtml('bi-shield-check')->assertSeeHtml('bi-check-lg')
                ->assertDontSeeHtml('<details');
        }
    }

    public function test_completed_challenge_has_a_seal_and_no_navigation_action(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->instance(DailyChallenge::class, \Mockery::mock(DailyChallenge::class)->shouldReceive('forToday')->andReturn([
            'text' => 'Registra agua 2 veces', 'done' => 2, 'target' => 2, 'completed' => true,
            'url' => route('water.daily'), 'checkin' => false,
        ])->getMock());
        Livewire::test(GameBalls::class)->assertSee('Cumplido. Mañana hay otro.')
            ->assertSeeHtml('game-challenge-quest__seal')->assertDontSee('Ir a Hidratación');
    }
}
