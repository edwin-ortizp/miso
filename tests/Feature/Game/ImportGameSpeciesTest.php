<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameSpecies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGameSpeciesTest extends TestCase
{
    use RefreshDatabase;

    private function file(mixed $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'species');
        file_put_contents($path, is_string($data) ? $data : json_encode($data));

        return $path;
    }

    public function test_valid_catalog_is_imported_and_updated_by_number(): void
    {
        $path = $this->file([
            ['number' => 1, 'name' => 'Brotelo', 'line_key' => 'brotelo', 'image_key' => '1.png'],
            ['number' => 2, 'name' => 'Brotalia', 'line_key' => 'brotelo', 'stage' => 2, 'rarity' => 'uncommon'],
        ]);

        $this->artisan('game:import-species', ['file' => $path])->assertSuccessful();
        $this->artisan('game:import-species', ['file' => $this->file([['number' => 1, 'name' => 'Brotelo B', 'line_key' => 'brotelo']])])->assertSuccessful();

        $this->assertSame(2, GameSpecies::count());
        $this->assertSame('Brotelo B', GameSpecies::where('number', 1)->value('name'));
        $this->assertSame(2, GameSpecies::where('number', 2)->value('stage'));
    }

    public function test_profile_fields_and_evolves_from_by_name_are_imported(): void
    {
        $path = $this->file([
            ['number' => 1, 'name' => 'Brotelo', 'line_key' => 'brotelo', 'description' => 'Duerme al sol.', 'types' => ['planta'], 'base_stats' => ['hp' => 45, 'speed' => 45], 'evolves_from' => null, 'generation' => 1],
            ['number' => 2, 'name' => 'Brotalia', 'line_key' => 'brotelo', 'stage' => 2, 'evolves_from' => 'Brotelo', 'evolve_level' => 12],
        ]);

        $this->artisan('game:import-species', ['file' => $path])->assertSuccessful();

        $first = GameSpecies::where('number', 1)->first();
        $this->assertSame('Duerme al sol.', $first->description);
        $this->assertSame(['planta'], $first->types);
        $this->assertSame(45, $first->stats['hp']);
        $second = GameSpecies::where('number', 2)->first();
        $this->assertSame(1, $second->evolves_from_number);
        $this->assertSame(12, $second->requiredLevel());
    }

    public function test_invalid_catalog_writes_nothing(): void
    {
        $path = $this->file([
            ['number' => 1, 'name' => 'Ok', 'line_key' => 'ok'],
            ['number' => 2, 'name' => 'Mala', 'line_key' => 'Con Espacios', 'rarity' => 'mítica'],
        ]);

        $this->artisan('game:import-species', ['file' => $path])->assertFailed();
        $this->artisan('game:import-species', ['file' => $this->file('no es json')])->assertFailed();

        $this->assertSame(0, GameSpecies::count());
    }
}
