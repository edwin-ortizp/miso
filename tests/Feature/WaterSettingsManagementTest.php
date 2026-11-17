<?php

namespace Tests\Feature;

use App\Livewire\Water\WaterSettings;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaterSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_the_drink_types_card_and_the_section_nav(): void
    {
        $user = User::factory()->create(['daily_water_goal' => 2500]);
        $this->actingAs($user);
        DrinkType::create(['name' => 'Agua', 'icon' => '💧', 'hydration_factor' => 1]);

        $this->get('/water/settings')->assertOk()
            ->assertSee('Tipos de bebida')
            ->assertSee('Nueva bebida')
            ->assertSee('Secciones de Ajustes de hidratación')
            ->assertSee('Meta diaria')
            ->assertSee('Categorías')
            ->assertDontSee('Gestionar categorías');
    }

    public function test_goal_section_shows_and_saves_the_daily_goal(): void
    {
        $user = User::factory()->create(['daily_water_goal' => 2500]);
        $this->actingAs($user);

        $this->get('/water/settings/goal')->assertOk()->assertSee('2.500')->assertSee('Nueva meta (ml)');

        Livewire::test(WaterSettings::class, ['section' => 'goal'])
            ->set('dailyWaterGoal', 3000)->call('saveGoal')->assertHasNoErrors()
            ->assertSet('message', 'Meta diaria actualizada.');

        $this->assertSame(3000, $user->fresh()->daily_water_goal);
    }

    public function test_categories_section_manages_drink_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/water/settings/categories')->assertOk()->assertSee('Categorías · Bebidas')->assertSee('Crear categoría');

        Livewire::test(WaterSettings::class, ['section' => 'categories'])
            ->call('openCategoryForm')->assertSet('showCategoryForm', true)
            ->set('categoryName', 'Calientes')->call('saveCategory')->assertHasNoErrors()
            ->assertSet('showCategoryForm', false)
            ->assertSee('Calientes')
            ->call('openCategoryForm')->set('categoryName', 'calientes')->call('saveCategory')->assertHasErrors('categoryName');

        $this->assertDatabaseHas('user_categories', ['user_id' => $user->id, 'domain' => 'drinks', 'name' => 'Calientes']);
    }

    public function test_unknown_section_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WaterSettings::class, ['section' => 'nope'])->assertStatus(404);
    }

    public function test_fab_dialog_creates_a_drink_type_and_closes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(WaterSettings::class)
            ->call('openForm')
            ->assertSet('showForm', true)
            ->assertSet('editingId', null)
            ->set('icon', '🫧')
            ->set('name', 'Agua con gas')
            ->set('hydrationFactor', '1.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSet('message', 'Bebida creada.')
            ->assertSee('Agua con gas');

        $this->assertDatabaseHas('drink_types', ['user_id' => $user->id, 'name' => 'Agua con gas', 'icon' => '🫧']);
    }

    public function test_edit_reuses_the_dialog_with_the_stored_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $type = DrinkType::create(['name' => 'Café', 'icon' => '☕', 'hydration_factor' => 0.8]);

        Livewire::test(WaterSettings::class)
            ->call('openForm', $type->id)
            ->assertSet('showForm', true)
            ->assertSet('editingId', $type->id)
            ->assertSet('name', 'Café')
            ->assertSet('icon', '☕')
            ->assertSet('hydrationFactor', '0.80')
            ->set('name', 'Café descafeinado')
            ->set('hydrationFactor', '0.95')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSet('message', 'Bebida actualizada.');

        $this->assertDatabaseHas('drink_types', ['id' => $type->id, 'name' => 'Café descafeinado', 'hydration_factor' => 0.95]);
    }

    public function test_dialog_validates_required_fields_and_stays_open(): void
    {
        $this->actingAs(User::factory()->create());
        DrinkType::create(['name' => 'Agua', 'icon' => '💧', 'hydration_factor' => 1]);

        Livewire::test(WaterSettings::class)
            ->call('openForm')
            ->set('icon', '')
            ->set('name', '')
            ->set('hydrationFactor', '')
            ->call('save')
            ->assertHasErrors(['icon' => 'required', 'name' => 'required', 'hydrationFactor' => 'required'])
            ->assertSet('showForm', true)
            ->set('icon', '💧')
            ->set('name', 'Agua')
            ->set('hydrationFactor', '10')
            ->call('save')
            ->assertHasErrors(['name' => 'unique', 'hydrationFactor' => 'max']);
    }

    public function test_search_filters_chips_and_sort_drive_the_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $water = DrinkType::create(['name' => 'Agua', 'icon' => '💧', 'hydration_factor' => 1]);
        DrinkType::create(['name' => 'Bebida isotónica', 'icon' => '⚡', 'hydration_factor' => 1.1]);
        DrinkType::create(['name' => 'Cerveza', 'icon' => '🍺', 'hydration_factor' => 0.4]);
        DrinkLog::create(['date' => now()->toDateString(), 'drink_type' => 'Agua', 'amount' => 250, 'hydration_value' => 250, 'time' => '08:00', 'timestamp' => now()->timestamp, 'drink_type_id' => $water->id]);

        $component = Livewire::test(WaterSettings::class)
            ->call('applyFilters', 'high', 'all')
            ->assertSee('Bebida isotónica')
            ->assertDontSee('Cerveza')
            ->assertSee('Factor: Hidrata más (&gt; 1)', false)
            ->call('removeFilter', 'factor')
            ->assertSee('Cerveza')
            ->call('applyFilters', 'all', 'used')
            ->assertSee('Uso: Con registros')
            ->assertDontSee('Cerveza')
            ->call('clearFilters')
            ->assertSet('usage', 'all')
            ->set('search', 'cerv')
            ->assertSee('Cerveza')
            ->assertDontSee('Bebida isotónica')
            ->set('search', '')
            ->set('sort', 'factor')
            ->assertSee('Mayor factor');

        $component->assertViewHas('drinkTypes', fn ($types) => $types->pluck('name')->all() === ['Bebida isotónica', 'Agua', 'Cerveza']);
        $component->set('sort', 'logs')
            ->assertViewHas('drinkTypes', fn ($types) => $types->first()->name === 'Agua');
    }

    public function test_drink_types_with_logs_cannot_be_deleted_and_other_users_types_are_hidden(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $private = DrinkType::create(['name' => 'Bebida privada', 'icon' => '🥤', 'hydration_factor' => 0.8]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $coffee = DrinkType::create(['name' => 'Café', 'icon' => '☕', 'hydration_factor' => 0.8]);
        DrinkLog::create(['date' => now()->toDateString(), 'drink_type' => 'Café', 'amount' => 250, 'hydration_value' => 200, 'time' => '08:00', 'timestamp' => now()->timestamp, 'drink_type_id' => $coffee->id]);

        Livewire::test(WaterSettings::class)
            ->assertDontSee('Bebida privada')
            ->call('openForm', $private->id)
            ->assertSet('showForm', false)
            ->assertSet('message', 'La bebida ya no está disponible.')
            ->call('delete', $coffee->id)
            ->assertSet('message', 'No se puede eliminar una bebida con registros históricos.');

        $this->assertDatabaseHas('drink_types', ['id' => $coffee->id]);
        $this->assertDatabaseHas('drink_types', ['id' => $private->id]);
    }
}
