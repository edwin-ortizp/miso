<?php

namespace Tests\Unit\Game;

use App\Models\Game\GameSpecies;
use App\Services\Game\EncounterRoller;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class EncounterRollerTest extends TestCase
{
    private function species(): Collection
    {
        return collect([
            ['number' => 1, 'line_key' => 'a', 'rarity' => 'common'],
            ['number' => 3, 'line_key' => 'b', 'rarity' => 'common'],
            ['number' => 5, 'line_key' => 'c', 'rarity' => 'uncommon'],
        ])->map(fn ($row) => (new GameSpecies)->forceFill($row));
    }

    public function test_same_seed_gives_same_species(): void
    {
        $roller = new EncounterRoller;
        $weights = ['common' => 60, 'uncommon' => 30];

        $first = $roller->roll($this->species(), [], $weights, false, 42);
        $second = $roller->roll($this->species(), [], $weights, false, 42);

        $this->assertSame($first->number, $second->number);
    }

    public function test_force_new_always_picks_an_unknown_line(): void
    {
        $roller = new EncounterRoller;
        foreach (range(1, 50) as $seed) {
            $picked = $roller->roll($this->species(), ['a', 'b'], ['common' => 99, 'uncommon' => 1], true, $seed);
            $this->assertSame('c', $picked->line_key);
        }
    }

    public function test_force_new_falls_back_when_everything_is_known(): void
    {
        $picked = (new EncounterRoller)->roll($this->species(), ['a', 'b', 'c'], ['common' => 1], true, 7);

        $this->assertNotNull($picked);
    }

    public function test_empty_catalog_returns_null(): void
    {
        $this->assertNull((new EncounterRoller)->roll(collect(), [], ['common' => 1], false, 1));
    }

    public function test_zero_weight_rarity_is_never_picked(): void
    {
        $roller = new EncounterRoller;
        foreach (range(1, 50) as $seed) {
            $this->assertSame('common', $roller->roll($this->species(), [], ['common' => 1, 'uncommon' => 0], false, $seed)->rarity);
        }
    }
}
