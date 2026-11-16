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

    /** CHG-135: la vista previa de compras carga las recetas planificadas por lotes. */
    public function test_shopping_preview_does_not_query_more_as_planned_meals_grow(): void
    {
        $plan = function (int $i): void {
            $recipe = Recipe::create(['name' => "Plato {$i}", 'meal_type' => 'almuerzo', 'servings' => 1]);
            $product = ShoppingItem::create(['name' => "Insumo {$i}", 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);
            \App\Models\RecipeIngredient::create(['recipe_id' => $recipe->id, 'shopping_item_id' => $product->id, 'quantity' => 100, 'unit' => 'g']);
            $entry = \App\Models\MealPlanEntry::create(['date' => today()->toDateString(), 'meal_type' => 'almuerzo', 'status' => 'planned']);
            $entry->items()->create(['recipe_id' => $recipe->id, 'name' => $recipe->name, 'portions' => 2, 'position' => 0]);
        };
        $preview = fn (): array => app(MealNeeds::class)->preview($this->user->id, today()->toDateString(), today()->toDateString());

        $plan(1);
        $before = count($this->queries($preview));
        foreach (range(2, 7) as $i) {
            $plan($i);
        }
        $result = null;
        $after = count($this->queries(function () use ($preview, &$result) {
            $result = $preview();
        }));

        $this->assertSame($before, $after, "La vista previa pasó de {$before} a {$after} consultas al planificar 6 comidas más.");
        $this->assertCount(7, $result['needs']);
        $this->assertSame(200.0, (float) $result['needs'][0]['quantity']);
        $this->assertSame([], $result['warnings']);
    }

    /** CHG-135: barra de estado, temporizador global y guardián comparten una lectura de los ajustes de Pomodoro. */
    public function test_pomodoro_settings_are_read_once_per_request_and_never_stale(): void
    {
        $settings = app(\App\Support\PomodoroSettings::class);
        $this->assertSame(\App\Support\PomodoroSettings::DEFAULTS, $settings->for());

        $queries = $this->queries(function () use ($settings) {
            $settings->for();
            app(\App\Support\PomodoroSettings::class)->goalFor(today());
        });
        $this->assertSame([], $queries);

        Livewire::test(\App\Livewire\Pomodoro\PomodoroSettings::class)
            ->set('weekdayGoal', 90)->set('weekendGoal', 45)->call('save')->assertHasNoErrors();

        $this->assertSame(90, $settings->for()['weekday_goal_minutes']);
        $this->assertSame(45, $settings->goalFor(now()->next('Saturday')));
        $this->assertSame(25, $settings->for()['work_duration_minutes']);

        $other = User::factory()->create();
        $this->assertSame(300, $settings->for($other->id)['weekday_goal_minutes'], 'Los ajustes de una persona no se mezclan con los de otra.');
    }

    /** CHG-135: un catálogo de categorías se lee una vez por pantalla y se actualiza al cambiar. */
    public function test_a_category_catalog_is_read_once_and_refreshed_after_a_change(): void
    {
        \App\Support\CategoryCatalog::seedFor($this->user->id);
        $first = \App\Support\CategoryCatalog::options('drinks');
        $this->assertNotEmpty($first);

        $this->assertSame([], $this->queries(fn () => $this->assertSame($first, \App\Support\CategoryCatalog::options('drinks'))));

        $category = \App\Models\UserCategory::create(['user_id' => $this->user->id, 'domain' => 'drinks', 'key' => 'kombucha', 'name' => 'Kombucha', 'normalized_name' => 'kombucha']);
        $this->assertSame('Kombucha', \App\Support\CategoryCatalog::options('drinks')['kombucha'] ?? null);

        $category->delete();
        $this->assertArrayNotHasKey('kombucha', \App\Support\CategoryCatalog::options('drinks'));
    }

    /** CHG-135: el ícono de cada fila de Hidratación no cuesta una consulta por registro. */
    public function test_water_daily_does_not_query_the_drink_type_per_row(): void
    {
        $types = collect(['Agua', 'Café', 'Té'])->map(fn (string $name) => \App\Models\DrinkType::create(['name' => $name, 'hydration_factor' => 1, 'icon' => '💧']));
        $log = function (int $i) use ($types): void {
            $type = $types[$i % 3];
            \App\Models\DrinkLog::create(['date' => today()->toDateString(), 'drink_type' => $type->name, 'drink_type_id' => $type->id,
                'amount' => 250, 'hydration_value' => 250, 'time' => '08:00', 'timestamp' => now()->timestamp + $i]);
        };
        $render = fn (): int => count($this->queries(fn () => Livewire::test(\App\Livewire\Water\WaterDaily::class)->assertSee('Agua')));

        $log(0);
        $render(); // la primera visita prepara catálogos por defecto
        $before = $render();
        foreach (range(1, 5) as $i) {
            $log($i);
        }

        $this->assertSame($before, $render(), 'Más registros con tipos distintos no deben sumar consultas en Hidratación.');
    }
}
