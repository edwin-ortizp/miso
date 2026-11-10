<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameSpecies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSpeciesDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['game.description_cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
    }

    private function species(int $number, ?string $description = null): GameSpecies
    {
        return GameSpecies::create(['number' => $number, 'name' => 'Especie '.$number, 'line_key' => 'line-'.$number, 'description' => $description]);
    }

    private function payload(int $id): array
    {
        return ['id' => $id, 'flavor_text_entries' => [
            ['language' => ['name' => 'en'], 'flavor_text' => 'English', 'version' => ['url' => 'https://pokeapi.co/api/v2/version/1/']],
            ['language' => ['name' => 'es'], 'flavor_text' => 'Otra versión', 'version' => ['url' => 'https://pokeapi.co/api/v2/version/8/']],
            ['language' => ['name' => 'es'], 'flavor_text' => "Duerme\n al\fsol.", 'version' => ['url' => 'https://pokeapi.co/api/v2/version/5/']],
        ]];
    }

    public function test_dry_run_caches_spanish_and_apply_only_completes_missing_descriptions(): void
    {
        $existing = $this->species(1, 'Texto propio');
        $missing = $this->species(2);
        Http::fake(['*pokemon-species/2/' => Http::response($this->payload(2))]);
        $this->artisan('game:sync-species-descriptions', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($missing->fresh()->description);
        $this->artisan('game:sync-species-descriptions')->assertSuccessful();
        $this->assertSame('Duerme al sol.', $missing->fresh()->description);
        $this->assertSame('Texto propio', $existing->fresh()->description);
        Http::assertSentCount(1);
    }

    public function test_missing_spanish_is_skipped_and_number_filter_is_respected(): void
    {
        $one = $this->species(1);
        $two = $this->species(2);
        Http::fake(['*pokemon-species/2/' => Http::response(['id' => 2, 'flavor_text_entries' => []])]);
        $this->artisan('game:sync-species-descriptions', ['--number' => 2])->assertSuccessful();
        $this->assertNull($one->fresh()->description);
        $this->assertNull($two->fresh()->description);
        Http::assertSentCount(1);
    }

    public function test_http_errors_and_wrong_species_never_write(): void
    {
        $one = $this->species(1);
        $two = $this->species(2);
        Http::fake([
            '*pokemon-species/1/' => Http::response([], 503),
            '*pokemon-species/2/' => Http::response($this->payload(3)),
        ]);
        $this->artisan('game:sync-species-descriptions')->assertFailed();
        $this->assertNull($one->fresh()->description);
        $this->assertNull($two->fresh()->description);
    }

    public function test_concurrent_description_is_preserved_and_invalid_number_makes_no_request(): void
    {
        $species = $this->species(1);
        Http::fake(function () use ($species) {
            $species->update(['description' => 'Edición local durante la consulta']);

            return Http::response($this->payload(1));
        });
        $this->artisan('game:sync-species-descriptions', ['--number' => 'abc'])->assertFailed();
        Http::assertNothingSent();
        $this->artisan('game:sync-species-descriptions')->assertSuccessful();
        $this->assertSame('Edición local durante la consulta', $species->fresh()->description);
    }
}
