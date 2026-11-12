<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskFlow;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\GameSettings;
use App\Services\Game\ModuleGuardian;
use App\Services\Game\WeekPlanner;
use App\Services\Meal\MealNeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Game\GameTestHelpers;
use Tests\TestCase;

/**
 * Presupuestos de consultas del backend (CHG-095). Fijan que las lecturas más
 * frecuentes no se repitan dentro de una petición, no bloqueen al leer y no
 * crezcan con el volumen de datos.
 */
class BackendQueryBudgetTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @return array<int, string> consultas ejecutadas por `$work` */
    private function queries(callable $work): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $work();
        } finally {
            $log = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
        }

        return $log;
    }

    private function companion(string $name): GameCompanion
    {
        $species = GameSpecies::where('name', $name)->firstOrFail();

        return GameCompanion::create(['line_key' => $species->line_key, 'species_id' => $species->id, 'level' => 1, 'first_met_at' => now()]);
    }

    public function test_reading_the_week_does_not_lock_or_write_once_the_team_is_settled(): void
    {
        $this->seedSpecies();
        $this->companion('Chispin');
        $this->companion('Rocon');
        $planner = app(WeekPlanner::class);
        $week = $planner->forWeek($this->user->id, now());

        $queries = $this->queries(fn () => $this->assertTrue($planner->forWeek($this->user->id, now())->is($week)));

        $this->assertCount(3, $queries, 'Leer la semana debe costar: semana, equipo y comprobar que no quedan candidatos.');
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', $query);
            $this->assertStringNotContainsStringIgnoringCase('for update', $query);
        }
    }

    public function test_a_new_companion_still_joins_an_incomplete_team(): void
    {
        $this->seedSpecies();
        $this->companion('Chispin');
        $planner = app(WeekPlanner::class);
        $week = $planner->forWeek($this->user->id, now());
        $this->assertSame(1, $week->members()->count());

        $this->companion('Rocon');

        $this->assertSame(2, $planner->forWeek($this->user->id, now())->members()->count());
    }

    public function test_asking_for_the_guardian_again_in_the_same_request_costs_nothing(): void
    {
        $this->seedSpecies();
        $this->companion('Chispin');
        $guardians = app(ModuleGuardian::class);
        // La primera llamada arma la semana (y por eso olvida lo leído); la segunda ya solo lee.
        $guardians->pick($this->user->id, 'tasks');
        $first = $guardians->pick($this->user->id, 'tasks');

        $queries = $this->queries(function () use ($guardians, $first) {
            $this->assertSame($first['name'], $guardians->pick($this->user->id, 'habits')['name']);
            $this->assertSame($first['name'], app(ModuleGuardian::class)->pick($this->user->id, 'water')['name']);
        });

        $this->assertSame([], $queries);
    }

    public function test_the_guardian_and_the_settings_never_serve_stale_data_after_a_change(): void
    {
        $this->seedSpecies();
        $this->companion('Chispin');
        $guardians = app(ModuleGuardian::class);
        $this->assertSame('Chispin', $guardians->pick($this->user->id, 'tasks')['name']);

        GameCompanion::query()->update(['level' => 1]);
        $rocon = $this->companion('Rocon');
        $rocon->update(['level' => 40]);
        $settings = app(GameSettings::class);
        $this->assertTrue($settings->for($this->user->id)['enabled']);
        $settings->save($this->user->id, ['enabled' => false]);

        $this->assertFalse($settings->for($this->user->id)['enabled']);
        $this->assertNull($guardians->pick($this->user->id, 'tasks'));

        $settings->save($this->user->id, ['enabled' => true]);
        $this->assertContains($guardians->pick($this->user->id, 'tasks')['name'], ['Chispin', 'Rocon']);
        $this->assertSame(2, app(WeekPlanner::class)->forWeek($this->user->id, now())->members()->count());
    }

    public function test_meal_suggestions_do_not_query_more_as_recipes_grow(): void
    {
        $addRecipe = function (int $i): void {
            $recipe = Recipe::create(['name' => "Receta {$i}", 'meal_type' => 'almuerzo', 'servings' => 1]);
            foreach (['A', 'B'] as $suffix) {
                $product = ShoppingItem::create(['name' => "Producto {$i}{$suffix}", 'base_unit' => 'g', 'status' => 'available', 'stock' => 500, 'to_buy' => 0]);
                $product->variants()->create(['content' => 500]);
                $recipe->recipeIngredients()->create(['shopping_item_id' => $product->id, 'quantity' => 100, 'unit' => 'g']);
            }
        };
        $measure = fn (): int => count($this->queries(fn () => app(MealNeeds::class)->suggestions($this->user->id)));

        $addRecipe(1);
        $withOne = $measure();
        foreach (range(2, 8) as $i) {
            $addRecipe($i);
        }
        $withEight = $measure();

        $this->assertSame($withOne, $withEight, "Las sugerencias pasaron de {$withOne} a {$withEight} consultas al añadir 7 recetas.");

        $recipes = collect(app(MealNeeds::class)->suggestions($this->user->id)['recipes']);
        $this->assertCount(8, $recipes);
        $this->assertTrue($recipes->every(fn ($recipe) => $recipe['can_cook'] && count($recipe['ingredients']) === 2));
    }

    public function test_task_flow_does_not_query_empty_categories(): void
    {
        TaskCategory::create(['key' => 'trabajo', 'name' => 'Trabajo']);
        Task::create(['title' => 'Pendiente', 'category' => 'trabajo']);
        Task::create(['title' => 'Hecha', 'category' => 'trabajo', 'completed' => true, 'completed_at' => now()]);
        Task::create(['title' => 'Suelta']);

        $render = fn (): int => count($this->queries(fn () => Livewire::test(TaskFlow::class)
            ->assertSee('Pendiente')->assertSee('Hecha')->assertSee('Suelta')));

        $before = $render();
        foreach (range(1, 6) as $i) {
            TaskCategory::create(['key' => "vacia-{$i}", 'name' => "Vacía {$i}"]);
        }

        $this->assertSame($before, $render(), 'Las categorías sin tareas no deben generar consultas propias en Flujo.');
    }

    public function test_saving_a_task_updates_its_row_once_and_still_bumps_the_caldav_revision(): void
    {
        $task = Task::create(['title' => 'Original'])->fresh();
        $revision = (int) $task->caldav_revision;

        $queries = $this->queries(fn () => $task->update(['title' => 'Editada']));

        $updates = array_filter($queries, fn (string $query) => str_starts_with($query, 'update "tasks"'));
        $this->assertCount(1, $updates);
        $this->assertSame($revision + 1, (int) $task->fresh()->caldav_revision);
        $this->assertSame('Editada', $task->fresh()->title);
    }
}
