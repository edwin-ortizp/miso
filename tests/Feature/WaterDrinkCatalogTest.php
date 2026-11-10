<?php

namespace Tests\Feature;

use App\Livewire\Water\WaterDaily;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaterDrinkCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_edit_a_drink_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(WaterDaily::class)
            ->call('openCatalog')
            ->call('openDrinkTypeForm')
            ->set('catalogDrinkName', 'Agua con limón')
            ->set('catalogDrinkIcon', '🍋')
            ->set('catalogHydrationFactor', '1.00')
            ->call('saveDrinkType')
            ->assertSet('catalogMessage', 'Bebida creada.')
            ->assertSee('Agua con limón');

        $drinkType = DrinkType::where('name', 'Agua con limón')->firstOrFail();

        Livewire::test(WaterDaily::class)
            ->call('openDrinkTypeForm', $drinkType->id)
            ->set('catalogDrinkName', 'Agua cítrica')
            ->set('catalogDrinkIcon', '🍊')
            ->set('catalogHydrationFactor', '0.90')
            ->call('saveDrinkType');

        $this->assertDatabaseHas('drink_types', [
            'id' => $drinkType->id,
            'user_id' => $user->id,
            'name' => 'Agua cítrica',
            'icon' => '🍊',
            'hydration_factor' => 0.90,
        ]);
    }

    public function test_catalog_requires_a_unique_name_and_non_negative_hydration_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DrinkType::create(['name' => 'Agua', 'icon' => '💧', 'hydration_factor' => 1]);

        Livewire::test(WaterDaily::class)
            ->call('openDrinkTypeForm')
            ->set('catalogDrinkName', 'Agua')
            ->set('catalogDrinkIcon', '💧')
            ->set('catalogHydrationFactor', '-0.10')
            ->call('saveDrinkType')
            ->assertHasErrors([
                'catalogDrinkName' => 'unique',
                'catalogHydrationFactor' => 'min',
            ]);
    }

    public function test_user_cannot_edit_or_delete_another_users_drink_type(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $drinkType = DrinkType::create(['name' => 'Bebida privada', 'icon' => '🥤', 'hydration_factor' => 0.8]);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        Livewire::test(WaterDaily::class)
            ->call('openDrinkTypeForm', $drinkType->id)
            ->assertSet('editingDrinkTypeId', null)
            ->call('deleteDrinkType', $drinkType->id)
            ->assertSet('catalogMessage', 'La bebida ya no está disponible.');

        $this->assertDatabaseHas('drink_types', ['id' => $drinkType->id, 'user_id' => $owner->id]);
    }

    public function test_catalog_cannot_delete_a_drink_type_with_historical_logs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $drinkType = DrinkType::create(['name' => 'Café', 'icon' => '☕', 'hydration_factor' => 0.7]);
        DrinkLog::create([
            'date' => now()->toDateString(),
            'drink_type' => 'Café',
            'amount' => 250,
            'hydration_value' => 175,
            'time' => '08:00',
            'timestamp' => now()->timestamp,
            'drink_type_id' => $drinkType->id,
        ]);

        Livewire::test(WaterDaily::class)
            ->call('deleteDrinkType', $drinkType->id)
            ->assertSet('catalogMessage', 'No se puede eliminar una bebida con registros históricos.');

        $this->assertDatabaseHas('drink_types', ['id' => $drinkType->id]);
    }

    public function test_editing_a_drink_type_does_not_change_historical_logs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $drinkType = DrinkType::create(['name' => 'Té', 'icon' => '🍵', 'hydration_factor' => 0.85]);
        $log = DrinkLog::create([
            'date' => now()->subDay()->toDateString(),
            'drink_type' => 'Té',
            'amount' => 250,
            'hydration_value' => 213,
            'time' => '09:00',
            'timestamp' => now()->subDay()->timestamp,
            'drink_type_id' => $drinkType->id,
        ]);

        Livewire::test(WaterDaily::class)
            ->call('openDrinkTypeForm', $drinkType->id)
            ->set('catalogDrinkName', 'Té verde')
            ->set('catalogDrinkIcon', '🫖')
            ->set('catalogHydrationFactor', '0.90')
            ->call('saveDrinkType');

        $this->assertDatabaseHas('drink_logs', [
            'id' => $log->id,
            'drink_type' => 'Té',
            'hydration_value' => 213,
        ]);
    }

    public function test_catalog_changes_are_available_in_the_water_registration_ui(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $drinkType = DrinkType::create(['name' => 'Infusión', 'icon' => '🫖', 'hydration_factor' => 0.85]);

        Livewire::test(WaterDaily::class)
            ->assertSee('Infusión')
            ->call('openForm')
            ->assertSee('Infusión')
            ->call('quickAdd', $drinkType->id, 250);

        $this->assertDatabaseHas('drink_logs', [
            'user_id' => $user->id,
            'drink_type_id' => $drinkType->id,
            'drink_type' => 'Infusión',
            'hydration_value' => 213,
        ]);
    }

    public function test_user_can_edit_a_hydration_logs_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $drinkType = DrinkType::create(['name' => 'Agua', 'icon' => '💧', 'hydration_factor' => 1]);
        $log = DrinkLog::create([
            'date' => '2026-07-20',
            'drink_type' => 'Agua',
            'amount' => 250,
            'hydration_value' => 250,
            'time' => '08:00',
            'timestamp' => Carbon::parse('2026-07-20 08:00')->timestamp,
            'drink_type_id' => $drinkType->id,
        ]);

        Livewire::test(WaterDaily::class, ['date' => '2026-07-20'])
            ->call('openForm', $log->id)
            ->assertSet('time', '08:00')
            ->set('time', '14:35')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('drink_logs', [
            'id' => $log->id,
            'time' => '14:35',
            'timestamp' => Carbon::parse('2026-07-20 14:35')->timestamp,
        ]);
    }
    public function test_closing_a_draft_clears_values_and_errors_without_saving(): void
    {
        $this->actingAs(User::factory()->create());
        $drink = DrinkType::create(['name' => 'Té', 'icon' => '🍵', 'hydration_factor' => 0.9]);

        Livewire::test(WaterDaily::class)
            ->call('openForm')
            ->call('save')
            ->assertHasErrors('drinkTypeId')
            ->assertSet('showForm', true)
            ->set('drinkTypeId', $drink->id)
            ->set('amount', 330)
            ->set('time', '10:30')
            ->call('closeForm')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSet('drinkTypeId', '')
            ->assertSet('amount', 250)
            ->assertSet('time', '')
            ->assertSet('editingId', null)
            ->call('openForm')
            ->assertHasNoErrors()
            ->assertSet('drinkTypeId', '')
            ->assertSet('amount', 250);

        $this->assertDatabaseCount('drink_logs', 0);
    }

}
