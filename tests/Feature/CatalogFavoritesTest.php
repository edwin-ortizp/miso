<?php

namespace Tests\Feature;

use App\Models\CatalogFavorite;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Models\User;
use App\Services\Catalog\CatalogPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-150: favoritos personales y «más usados» de los catálogos de bebidas y ejercicios. */
class CatalogFavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function picker(): CatalogPicker
    {
        return app(CatalogPicker::class);
    }

    private function drink(string $name): DrinkType
    {
        return DrinkType::create(['name' => $name, 'icon' => '💧', 'hydration_factor' => 1]);
    }

    private function logDrinks(DrinkType $type, int $times, string $date = null): void
    {
        for ($i = 0; $i < $times; $i++) {
            DrinkLog::create(['date' => $date ?? now()->toDateString(), 'drink_type' => $type->name, 'drink_type_id' => $type->id, 'amount' => 250, 'hydration_value' => 250, 'time' => '08:00:00', 'timestamp' => now()]);
        }
    }

    public function test_favorites_are_personal_and_do_not_touch_the_catalog(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner);
        $coffee = $this->drink('Café');
        $before = $coffee->fresh()->getAttributes();

        $this->assertTrue($this->picker()->toggle('drinks', $coffee->id));

        $this->assertSame($before, $coffee->fresh()->getAttributes());
        $this->assertSame([$coffee->id], $this->picker()->favoriteIds('drinks'));

        $this->actingAs($other);
        $this->assertSame([], $this->picker()->favoriteIds('drinks'));
        $this->assertFalse($this->picker()->toggle('drinks', $coffee->id), 'un elemento ajeno no se puede marcar');
        $this->assertSame(1, CatalogFavorite::withoutGlobalScopes()->count());
    }

    public function test_toggle_marks_and_unmarks_without_duplicates(): void
    {
        $this->actingAs(User::factory()->create());
        $water = $this->drink('Agua');

        $this->picker()->set('drinks', $water->id, true);
        $this->picker()->set('drinks', $water->id, true);
        $this->assertSame(1, CatalogFavorite::count());

        $this->assertFalse($this->picker()->toggle('drinks', $water->id));
        $this->assertSame(0, CatalogFavorite::count());
    }

    public function test_ordered_puts_favorites_first_and_keeps_the_full_catalog(): void
    {
        $this->actingAs(User::factory()->create());
        $agua = $this->drink('Agua');
        $cafe = $this->drink('Café');
        $te = $this->drink('Té');
        $vino = $this->drink('Vino');

        $this->picker()->set('drinks', $vino->id, true);
        $this->picker()->set('drinks', $te->id, true);

        $this->assertSame(['Vino', 'Té', 'Agua', 'Café'], $this->picker()->ordered('drinks')->pluck('name')->all());
        $this->assertCount(4, $this->picker()->ordered('drinks'));
        $this->assertSame(['Vino', 'Té'], $this->picker()->favorites('drinks')->pluck('name')->all());
    }

    public function test_most_used_counts_logs_and_never_creates_favorites(): void
    {
        $this->actingAs(User::factory()->create());
        $agua = $this->drink('Agua');
        $cafe = $this->drink('Café');
        $te = $this->drink('Té');
        $this->logDrinks($agua, 5);
        $this->logDrinks($cafe, 3);
        $this->logDrinks($te, 1);
        $this->logDrinks($te, 9, now()->subDays(200)->toDateString());

        $used = $this->picker()->mostUsed('drinks');

        $this->assertSame([$agua->id => 5, $cafe->id => 3], $used, 'solo los frecuentes recientes');
        $this->assertSame(0, CatalogFavorite::count());

        $this->picker()->set('drinks', $agua->id, true);
        $this->assertSame(['Café'], $this->picker()->suggestions('drinks')->pluck('name')->all(), 'las sugerencias excluyen los favoritos');
    }

    public function test_quick_list_prefers_favorites_and_fills_with_most_used(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->drink('Agua');
        $b = $this->drink('Batido');
        $c = $this->drink('Café');
        $d = $this->drink('Té');
        $this->logDrinks($d, 4);
        $this->logDrinks($c, 2);
        $this->picker()->set('drinks', $b->id, true);

        $this->assertSame(['Batido', 'Té', 'Café'], $this->picker()->quick('drinks', 3)->pluck('name')->all());
    }

    public function test_exercise_catalog_works_with_the_same_service(): void
    {
        $this->actingAs(User::factory()->create());
        $run = ExerciseType::create(['name' => 'Correr', 'calories_per_hour' => 600, 'steps_equivalent' => 0, 'icon' => '🏃']);
        ExerciseType::create(['name' => 'Yoga', 'calories_per_hour' => 200, 'steps_equivalent' => 0, 'icon' => '🧘']);
        for ($i = 0; $i < 3; $i++) {
            ExerciseLog::create(['date' => now()->toDateString(), 'exercise_type_id' => $run->id, 'duration_minutes' => 30, 'calories_burned' => 300]);
        }

        $this->assertSame([$run->id => 3], $this->picker()->mostUsed('exercise'));
        $this->picker()->set('exercise', $run->id, true);
        $this->assertSame('Correr', $this->picker()->ordered('exercise')->first()->name);
    }

    public function test_deleting_an_item_cleans_its_favorite(): void
    {
        $this->actingAs(User::factory()->create());
        $type = $this->drink('Kéfir');
        $this->picker()->set('drinks', $type->id, true);

        $type->delete();

        $this->assertSame(0, CatalogFavorite::count());
    }

    public function test_unknown_domains_are_not_supported(): void
    {
        $this->assertFalse(CatalogPicker::supports('mood'));
        $this->assertTrue(CatalogPicker::supports('drinks'));
    }

    public function test_water_settings_marks_orders_filters_and_suggests_without_auto_favoriting(): void
    {
        $this->actingAs(User::factory()->create());
        $agua = $this->drink('Agua');
        $cafe = $this->drink('Café');
        $te = $this->drink('Té');
        $this->logDrinks($te, 4);

        $page = \Livewire\Livewire::test(\App\Livewire\Water\WaterSettings::class)
            ->assertSee('Sugeridas por tu uso')->assertSee('Agregar Té a favoritas')
            ->assertSee('Marcar Agua como favorito');
        $this->assertSame(0, CatalogFavorite::count(), 'las sugerencias nunca se vuelven favoritas solas');

        $page->call('toggleFavorite', 'drinks', $cafe->id)->assertSee('Quitar Café de favoritos');
        $this->assertSame(['Café', 'Agua', 'Té'], $page->viewData('drinkTypes')->getCollection()->pluck('name')->all());

        $page->call('toggleFavorite', 'drinks', $te->id)->assertDontSee('Agregar Té a favoritas');

        $page->set('onlyFavorites', true);
        $this->assertSame(['Café', 'Té'], $page->viewData('drinkTypes')->getCollection()->pluck('name')->all());

        $page->set('onlyFavorites', false)->set('sort', 'name');
        $this->assertSame(['Agua', 'Café', 'Té'], $page->viewData('drinkTypes')->getCollection()->pluck('name')->all());
    }

    public function test_exercise_settings_group_favorites_first_and_suggest_most_used(): void
    {
        $this->actingAs(User::factory()->create());
        $run = ExerciseType::create(['name' => 'Correr', 'calories_per_hour' => 600, 'steps_equivalent' => 0, 'icon' => '🏃', 'category' => 'cardio']);
        $yoga = ExerciseType::create(['name' => 'Yoga', 'calories_per_hour' => 200, 'steps_equivalent' => 0, 'icon' => '🧘', 'category' => 'flexibility']);
        for ($i = 0; $i < 3; $i++) {
            ExerciseLog::create(['date' => now()->toDateString(), 'exercise_type_id' => $run->id, 'duration_minutes' => 30, 'calories_burned' => 300]);
        }

        $page = \Livewire\Livewire::test(\App\Livewire\Exercise\ExerciseSettings::class)
            ->assertSee('Sugeridos por tu uso')->assertSee('Agregar Correr a favoritos');
        $this->assertSame(0, CatalogFavorite::count());

        $page->call('toggleFavorite', 'exercise', $yoga->id);
        $this->assertSame('__favorites', $page->viewData('groups')->keys()->first());
        $page->assertSee('Quitar Yoga de favoritos')->assertSee('Agregar Correr a favoritos');

        $page->set('onlyFavorites', true);
        $this->assertSame(['Yoga'], $page->viewData('types')->getCollection()->pluck('name')->all());
    }

    public function test_water_chat_and_default_drink_use_favorites_first(): void
    {
        $this->actingAs(User::factory()->create());
        $agua = $this->drink('Agua');
        $batido = $this->drink('Batido');
        $zumo = $this->drink('Zumo');
        $this->picker()->set('drinks', $zumo->id, true);

        $page = \Livewire\Livewire::test(\App\Livewire\Water\WaterDaily::class);
        $this->assertSame(['Zumo', 'Agua', 'Batido'], $page->viewData('quickDrinks')->pluck('name')->all(), 'el favorito va primero y el catálogo sigue completo');
        $this->assertSame(['Zumo', 'Agua', 'Batido'], $page->viewData('drinkTypes')->pluck('name')->all());
        $page->assertSee('★ ');

        $this->assertSame('Zumo', $this->picker()->defaultWater()->name, 'sin un favorito «agua», gana la primera favorita');
        $this->picker()->set('drinks', $agua->id, true);
        $this->assertSame('Agua', $this->picker()->defaultWater()->name, 'entre los favoritos se prefiere el agua');
        $this->picker()->set('drinks', $agua->id, false);
        $this->picker()->set('drinks', $zumo->id, false);
        $this->assertSame('Agua', $this->picker()->defaultWater()->name, 'sin favoritos, la heurística de siempre');
    }

    public function test_exercise_chat_offers_quick_chips_with_favorites_first(): void
    {
        $this->actingAs(User::factory()->create());
        ExerciseType::create(['name' => 'Correr', 'calories_per_hour' => 600, 'steps_equivalent' => 0, 'icon' => '🏃']);
        $yoga = ExerciseType::create(['name' => 'Yoga', 'calories_per_hour' => 200, 'steps_equivalent' => 0, 'icon' => '🧘']);
        $this->picker()->set('exercise', $yoga->id, true);

        $page = \Livewire\Livewire::test(\App\Livewire\Exercise\ExerciseDaily::class);
        $this->assertSame(['Yoga', 'Correr'], $page->viewData('quickExercises')->pluck('name')->all());

        $result = $page->instance()->quickAdd($yoga->id, 30);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('exercise_logs', ['exercise_type_id' => $yoga->id, 'duration' => 30]);
    }
}
