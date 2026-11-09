<?php

namespace Tests\Feature\Game;

use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\SeasonValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeasonValidatorTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        TaskCategory::create(['name' => 'Salud', 'key' => 'salud']);
    }

    private function validate(array $proposal): array
    {
        return app(SeasonValidator::class)->validate($proposal, auth()->id());
    }

    public static function validProposal(): array
    {
        return [
            'name' => 'Temporada del Bosque',
            'narrative' => 'Las criaturas del bosque despiertan.',
            'weeks' => 4,
            'missions' => ['types' => ['category_tasks', 'variety'], 'target' => [2, 5], 'focus' => ['salud']],
            'team_bonus' => ['salud' => 1.5, 'habits' => 1.2],
            'rarity_weights' => ['common' => 50, 'uncommon' => 35, 'rare' => 12, 'legendary' => 3],
            'featured_line' => 'brotelo',
        ];
    }

    public function test_valid_proposal_is_normalized(): void
    {
        $result = $this->validate(self::validProposal());

        $this->assertSame([], $result['errors']);
        $this->assertSame(['salud' => 1.5, 'habits' => 1.2], $result['config']['team_bonus']);
        $this->assertSame(['salud'], $result['config']['mission_focus']);
        $this->assertSame('brotelo', $result['config']['featured_line']);
        $this->assertSame(2, $result['config']['featured_weight']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidCases(): array
    {
        $base = self::validProposal();

        return [
            'bono fuera de rango' => [['team_bonus' => ['salud' => 3]] + $base, 'debe estar entre'],
            'categoría inexistente' => [['team_bonus' => ['viajes' => 1.2]] + $base, 'no existe'],
            'demasiados ámbitos' => [['team_bonus' => ['salud' => 1.1, 'hogar' => 1.1, 'habits' => 1.1]] + $base, 'hasta 2'],
            'legendary alto' => [['rarity_weights' => ['common' => 50, 'legendary' => 10]] + $base, 'legendary'],
            'pesos en cero' => [['rarity_weights' => ['common' => 0]] + $base, 'mayor que cero'],
            'semanas' => [['weeks' => 12] + $base, 'semanas'],
            'metas' => [['missions' => ['target' => [1, 9]]] + $base, 'metas'],
            'campo extra' => [$base + ['delete_progress' => true], 'no permitido'],
            'sin nombre' => [['name' => ''] + $base, 'nombre'],
            'línea inexistente' => [['featured_line' => 'nada'] + $base, 'línea destacada'],
        ];
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_proposals_are_rejected_with_reasons(array $proposal, string $expected): void
    {
        $result = $this->validate($proposal);

        $this->assertNotSame([], $result['errors']);
        $this->assertStringContainsString($expected, implode(' ', $result['errors']));
        $this->assertSame([], $result['config']);
    }
}
