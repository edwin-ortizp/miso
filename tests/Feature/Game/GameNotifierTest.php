<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameNotifier;
use App\Livewire\Game\GameSettingsPage;
use App\Models\User;
use App\Services\Game\GameSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameNotifierTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shows_toasts_and_dispatches_a_celebration_after_a_new_ball(): void
    {
        $notifier = Livewire::test(GameNotifier::class)->assertSet('toasts', []);

        $this->completeTask('Pagar arriendo');
        $notifier->dispatch('game-check')
            ->assertSee('¡Ganaste una ball!')
            ->assertSee('Pagar arriendo')
            ->assertSee('Abrir ball')
            ->assertSeeHtml('game-toast game-toast--ball')
            ->assertDispatched('game-celebrate');

        // Sin novedades no repite.
        $count = count($notifier->get('toasts'));
        $notifier->call('check')->assertCount('toasts', $count);
    }

    public function test_does_not_repeat_what_happened_before_mounting(): void
    {
        $this->completeTask('Antes');

        Livewire::test(GameNotifier::class)->call('check')->assertSet('toasts', [])->assertNotDispatched('game-celebrate');
    }

    public function test_celebration_is_random_among_enabled_and_differs_from_the_previous(): void
    {
        $this->gameSettings(['celebration_set' => ['cats', 'balloons']]);
        $notifier = Livewire::test(GameNotifier::class);

        $this->completeTask('A');
        $notifier->call('check');
        $first = $notifier->get('lastCelebration');
        $this->completeTask('B');
        $notifier->call('check');
        $second = $notifier->get('lastCelebration');

        $this->assertContains($first, ['cats', 'balloons']);
        $this->assertContains($second, ['cats', 'balloons']);
        $this->assertNotSame($first, $second);
    }

    public function test_disabled_celebrations_show_only_toasts(): void
    {
        $this->gameSettings(['celebrations' => false]);
        $notifier = Livewire::test(GameNotifier::class);

        $this->completeTask('A');
        $notifier->call('check')->assertSee('¡Ganaste una ball!')->assertNotDispatched('game-celebrate');
    }

    public function test_toasts_are_limited_and_dismissable(): void
    {
        $notifier = Livewire::test(GameNotifier::class);
        foreach (['A', 'B', 'C', 'D'] as $title) {
            $this->completeTask($title);
            $notifier->call('check');
        }

        $notifier->assertCount('toasts', GameNotifier::MAX_TOASTS);
        $id = $notifier->get('toasts')[0]['id'];
        $notifier->call('dismiss', $id)->assertCount('toasts', GameNotifier::MAX_TOASTS - 1);
    }

    public function test_other_users_events_never_appear(): void
    {
        $notifier = Livewire::test(GameNotifier::class);
        $me = auth()->user();
        $this->actingAs(User::factory()->create());
        $this->completeTask('Ajena');
        $this->actingAs($me);

        $notifier->call('check')->assertSet('toasts', []);
    }

    public function test_layout_renders_the_notifier_without_a_transformed_wrapper(): void
    {
        $html = $this->get(route('game.balls'))->assertOk()->assertSeeLivewire(GameNotifier::class)->getContent();

        $this->assertStringContainsString('class="game-toasts"', $html);
        $this->assertStringNotContainsString('game-notifier__wrap', $html);
    }

    public function test_celebration_settings_are_saved(): void
    {
        Livewire::test(GameSettingsPage::class)
            ->set('celebrations', true)
            ->set('celebrationSet', ['cats', 'confetti', 'inventada'])
            ->call('save');

        $settings = app(GameSettings::class)->for(auth()->id());
        $this->assertSame(['confetti', 'cats'], $settings['celebration_set']);
    }

    public function test_a_new_ball_plays_the_ball_sound_even_without_celebrations(): void
    {
        $this->gameSettings(['celebrations' => false]);
        $notifier = Livewire::test(GameNotifier::class)->assertSeeHtml('data-game-sound="1"');

        $this->completeTask('A');
        $notifier->call('check')->assertDispatched('game-sound', name: 'ball');
    }

    public function test_sounds_off_never_dispatch_a_sound(): void
    {
        $this->gameSettings(['sounds' => false]);
        $notifier = Livewire::test(GameNotifier::class)->assertSeeHtml('data-game-sound="0"');

        $this->completeTask('A');
        $notifier->call('check')->assertNotDispatched('game-sound');
    }

    public function test_sound_setting_is_saved_and_announced(): void
    {
        Livewire::test(GameSettingsPage::class)
            ->assertSee('Probar sonido')
            ->assertSet('sounds', true)
            ->set('sounds', false)
            ->call('save')
            ->assertDispatched('game-settings-saved');

        $this->assertFalse(app(GameSettings::class)->for(auth()->id())['sounds']);
        Livewire::test(GameNotifier::class)->assertSet('sounds', false);
    }
}
