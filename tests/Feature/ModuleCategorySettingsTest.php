<?php

namespace Tests\Feature;

use App\Livewire\Exercise\ExerciseSettings;
use App\Livewire\Mood\MoodSettings;
use App\Livewire\NegativeHabit\NegativeHabitSettings;
use App\Livewire\Relationship\RelationshipSettings;
use App\Livewire\Vehicle\VehicleCatalog;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-149: ajustes con secciones y categorías incrustadas, sin botón suelto «Gestionar categorías». */
class ModuleCategorySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: class-string, 3: string, 4: string}> */
    public static function categorySections(): array
    {
        return [
            'ejercicio' => ['exercise.settings.categories', 'categories', ExerciseSettings::class, 'exercise', 'Categorías · Ejercicios'],
            'ánimo' => ['mood.settings.categories', 'categories', MoodSettings::class, 'mood', 'Categorías · Estados de ánimo'],
            'vehículos' => ['vehicles.catalog.categories', 'categories', VehicleCatalog::class, 'maintenance', 'Categorías · Plantillas de mantenimiento'],
            'hábitos a evitar' => ['negative-habits.settings', 'categories', NegativeHabitSettings::class, 'negative-habits', 'Categorías · Hábitos a evitar'],
            'relaciones: personas' => ['relationships.settings', 'people', RelationshipSettings::class, 'contacts', 'Categorías · Contactos'],
            'relaciones: acontecimientos' => ['relationships.settings.events', 'events', RelationshipSettings::class, 'relationship-events', 'Categorías · Eventos de relaciones'],
        ];
    }

    /** @dataProvider categorySections */
    #[\PHPUnit\Framework\Attributes\DataProvider('categorySections')]
    public function test_category_section_renders_and_manages_categories(string $route, string $section, string $component, string $domain, string $title): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route($route))->assertOk()->assertSee($title)->assertSee('Crear categoría')->assertDontSee('Gestionar categorías');

        Livewire::test($component, ['section' => $section])
            ->call('openCategoryForm')->assertSet('showCategoryForm', true)
            ->set('categoryName', 'Mi categoría')->call('saveCategory')->assertHasNoErrors()
            ->assertSet('showCategoryForm', false)->assertSee('Mi categoría')
            ->call('openCategoryForm')->set('categoryName', 'MI CATEGORÍA')->call('saveCategory')->assertHasErrors('categoryName');

        $this->assertDatabaseHas('user_categories', ['user_id' => $user->id, 'domain' => $domain, 'name' => 'Mi categoría']);
    }

    public function test_a_category_in_use_cannot_be_deleted_from_a_module_section(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $root = UserCategory::where('domain', 'exercise')->firstOrFail();
        UserCategory::create(['user_id' => $user->id, 'domain' => 'exercise', 'name' => 'Hija', 'key' => 'hija', 'parent_id' => $root->id]);

        Livewire::test(ExerciseSettings::class, ['section' => 'categories'])
            ->call('deleteCategory', $root->id)->assertSee('Mueve primero');

        $this->assertDatabaseHas('user_categories', ['id' => $root->id]);
    }

    public function test_categories_are_personal_to_each_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        UserCategory::create(['user_id' => $owner->id, 'domain' => 'mood', 'name' => 'Solo mía', 'key' => 'solo-mia']);

        $this->actingAs($other);

        $this->get(route('mood.settings.categories'))->assertOk()->assertDontSee('Solo mía');
    }

    public function test_unknown_sections_are_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([ExerciseSettings::class, MoodSettings::class, VehicleCatalog::class, NegativeHabitSettings::class, RelationshipSettings::class] as $component) {
            Livewire::test($component, ['section' => 'nope'])->assertStatus(404);
        }
    }

    public function test_loose_manage_categories_buttons_are_gone_from_the_module_views(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['exercise.settings', 'mood.settings', 'vehicles.catalog', 'negative-habits', 'relationships', 'relationships.events', 'water.settings'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Ajustes de categorías', false);
        }
    }
}
