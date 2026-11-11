<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\Albums;
use App\Services\Game\MissionProposalValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** CHG-078: álbumes por tipo, conteos y recompensa única. */
class AlbumsTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        // Brotelo/Brotalia: planta · Chispin: electrico+volador · Gotin: agua · Rocon: roca (sin activar Chispazo).
        $types = [1 => ['planta'], 2 => ['planta'], 3 => ['electrico', 'volador'], 4 => ['electrico'], 5 => ['agua'], 6 => ['roca']];
        foreach ($types as $number => $list) {
            GameSpecies::where('number', $number)->update(['types' => json_encode($list)]);
        }
        GameSpecies::where('number', 4)->update(['active' => false]);
    }

    private function discover(int $number): void
    {
        GameDiscovery::create(['user_id' => auth()->id(), 'species_id' => GameSpecies::where('number', $number)->value('id'), 'discovered_at' => now()]);
    }

    private function album(string $type): ?array
    {
        return collect(app(Albums::class)->overview(auth()->id()))->firstWhere('type', $type);
    }

    private function albumBalls(): int
    {
        return GameEvent::where('kind', GameEvent::KIND_BONUS)->where('idempotency_key', 'like', 'album:%')->count();
    }

    public function test_overview_counts_found_and_total_per_active_type(): void
    {
        $this->discover(1);

        $this->assertSame(['found' => 1, 'total' => 2], array_intersect_key($this->album('planta'), ['found' => 0, 'total' => 0]));
        $this->assertSame('Eléctrico', $this->album('electrico')['label']);
        $this->assertSame(1, $this->album('electrico')['total'], 'La especie inactiva no cuenta.');
        $this->assertNull($this->album('fuego'), 'Un tipo sin especies no tiene álbum.');
    }

    public function test_two_types_count_in_both_albums(): void
    {
        $this->discover(3);

        $this->assertTrue($this->album('electrico')['complete']);
        $this->assertTrue($this->album('volador')['complete']);
    }

    public function test_completing_an_album_pays_one_ball_once(): void
    {
        $this->discover(1);
        $this->assertSame(0, $this->albumBalls());
        $this->discover(2);

        $this->assertSame(1, $this->albumBalls());
        $event = GameEvent::where('idempotency_key', 'album:planta')->first();
        $this->assertSame('¡Álbum Planta completo!', $event->effect['title']);
        $this->assertSame(1, GameEncounter::count());

        app(Albums::class)->awardIfComplete(auth()->id(), GameSpecies::where('number', 1)->value('id'));
        $this->assertSame(1, $this->albumBalls());
    }

    public function test_growing_catalog_reopens_the_album_without_paying_again(): void
    {
        $this->discover(5);
        $this->assertSame(1, $this->albumBalls());

        GameSpecies::create(['number' => 7, 'name' => 'Olin', 'line_key' => 'olin', 'stage' => 1, 'rarity' => 'common', 'image_key' => '7.png', 'types' => ['agua']]);
        $this->assertFalse($this->album('agua')['complete']);

        $this->discover(7);
        $this->assertTrue($this->album('agua')['complete']);
        $this->assertSame(1, $this->albumBalls());
    }

    public function test_album_complete_before_the_change_is_seeded_without_ball(): void
    {
        DB::table('game_album_awards')->insert(['user_id' => auth()->id(), 'album_key' => 'roca', 'species_total' => 1, 'awarded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->discover(6);

        $this->assertTrue($this->album('roca')['complete']);
        $this->assertSame(0, $this->albumBalls());
    }

    public function test_texts_fit_and_have_no_penalty_words(): void
    {
        foreach (['Álbumes', '7 de 14', 'Álbum Eléctrico · 7 de 14', 'Todos', 'Completo', '¡Álbum Psíquico completo!', 'Ganaste una ball.', 'Álbum Agua: 7 de 14 especies'] as $text) {
            $this->assertLessThanOrEqual(90, mb_strlen($text), $text);
            $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
        }
    }
}
