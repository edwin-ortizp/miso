<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameCollection;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameSpecies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-078 · T1/T2: franja de álbumes y filtro de la cuadrícula. */
class GameCollectionAlbumsTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameSpecies::whereIn('number', [1, 2])->update(['types' => json_encode(['planta'])]);
        GameSpecies::whereIn('number', [3, 4])->update(['types' => json_encode(['electrico'])]);
        GameDiscovery::create(['user_id' => auth()->id(), 'species_id' => GameSpecies::where('number', 1)->value('id'), 'discovered_at' => now()]);
    }

    public function test_strip_shows_albums_with_counts(): void
    {
        Livewire::test(GameCollection::class)
            ->assertSee('Álbumes')
            ->assertSeeHtml('aria-label="Álbum Planta: 1 de 2 especies"')
            ->assertSeeHtml('aria-label="Álbum Eléctrico: 0 de 2 especies"');
    }

    public function test_choosing_an_album_filters_the_grid_and_keeps_the_url(): void
    {
        Livewire::withQueryParams(['type' => 'planta'])->test(GameCollection::class)
            ->assertSet('type', 'planta')
            ->assertSee('Álbum Planta · 1 de 2')
            ->assertSee('Brotelo')
            ->assertDontSee('Chispazo');
    }

    public function test_album_counts_ignore_search_and_rarity_and_all_clears(): void
    {
        Livewire::test(GameCollection::class)
            ->set('rarity', 'rare')->set('search', 'zzz')
            ->call('$set', 'type', 'planta')
            ->assertSee('Álbum Planta · 1 de 2')
            ->call('$set', 'type', '')
            ->assertSet('rarity', 'rare')->assertSet('search', 'zzz')
            ->assertDontSee('Álbum Planta · 1 de 2');
    }
}
