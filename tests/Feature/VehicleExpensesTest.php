<?php

namespace Tests\Feature;

use App\Livewire\Vehicle\VehicleExpenses;
use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleExpenseCategory;
use App\Support\DefaultVehicleExpenseCategories;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleExpensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_categories_use_the_same_chip_in_table_and_mobile_cards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $vehicle = $user->vehicles()->create(['name' => 'Auto', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina', 'usage_unit' => 'km']);
        $category = VehicleExpenseCategory::create(['name' => 'Seguros', 'icon' => 'bi-shield']);
        $vehicle->expenses()->create(['vehicle_expense_category_id' => $category->id, 'spent_on' => today(), 'amount' => 10000]);
        $view = Livewire::test(VehicleExpenses::class, ['vehicle' => $vehicle->id])
            ->assertSeeHtml('md-catalog-chip--accent')->assertSeeHtml('bi-shield');
        $this->assertSame(2, substr_count($view->html(), 'md-catalog-chip--accent'));
    }

    public function test_registration_seeds_default_categories_and_restoring_is_idempotent(): void
    {
        $this->post(route('register'), [
            'name' => 'Conductora', 'email' => 'conductora@example.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
        ]);
        $user = User::where('email', 'conductora@example.com')->firstOrFail();
        $count = count(DefaultVehicleExpenseCategories::all());

        $this->assertSame($count, $user->vehicleExpenseCategories()->count());
        $this->assertSame(0, DefaultVehicleExpenseCategories::createFor($user));

        $user->vehicleExpenseCategories()->where('name', 'Llantas')->delete();
        $this->assertSame(1, DefaultVehicleExpenseCategories::createFor($user));
        $this->assertSame($count, $user->vehicleExpenseCategories()->count());
    }

    public function test_expenses_can_be_created_edited_filtered_and_deleted(): void
    {
        Carbon::setTestNow('2026-09-13 10:00:00');
        $user = User::factory()->create();
        DefaultVehicleExpenseCategories::createFor($user);
        $this->actingAs($user);
        $vehicle = $user->vehicles()->create(['name' => 'Cruze', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina', 'usage_unit' => 'km']);
        $insurance = VehicleExpenseCategory::where('name', 'Seguros')->firstOrFail();
        $tires = VehicleExpenseCategory::where('name', 'Llantas')->firstOrFail();

        $component = Livewire::test(VehicleExpenses::class, ['vehicle' => $vehicle->id])
            ->call('openExpenseForm')
            ->assertSet('expenseDate', '2026-09-13')
            ->set('expenseCategoryId', '')
            ->call('saveExpense')
            ->assertHasErrors(['expenseCategoryId' => 'required', 'expenseAmount' => 'required'])
            ->set('expenseCategoryId', $insurance->id)
            ->set('expenseAmount', 77300)
            ->set('expenseDescription', 'Cuota seguro todo riesgo')
            ->set('expenseProvider', 'Sura')
            ->set('expenseUsageReading', 142620)
            ->call('saveExpense')
            ->assertHasNoErrors()
            ->assertSet('showExpenseForm', false);

        $expense = VehicleExpense::firstOrFail();
        $this->assertSame('77300.00', $expense->amount);
        $this->assertNull($vehicle->fresh()->current_usage, 'La lectura del gasto es informativa.');

        $vehicle->expenses()->create(['vehicle_expense_category_id' => $tires->id, 'spent_on' => '2026-01-10', 'amount' => 185000, 'description' => 'Llanta de repuesto']);

        $component
            ->call('$refresh')
            ->assertViewHas('filteredAmount', 262300.0)
            ->assertViewHas('topCategory', fn ($top) => $top['name'] === 'Llantas')
            ->set('expensePeriod', '1m')
            ->assertViewHas('expenses', fn ($expenses) => $expenses->getCollection()->pluck('id')->all() === [$expense->id])
            ->call('clearExpenseFilters')
            ->set('expenseCategory', $tires->id)
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1 && $expenses->first()->description === 'Llanta de repuesto')
            ->call('clearExpenseFilters')
            ->set('expenseSearch', 'sura')
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1)
            ->call('clearExpenseFilters')
            ->call('openExpenseForm', $expense->id)
            ->assertSet('expenseAmount', 77300.0)
            ->set('expenseCategoryId', $tires->id)
            ->call('saveExpense')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('vehicle_expenses', ['id' => $expense->id, 'vehicle_expense_category_id' => $tires->id]);

        $component->call('deleteExpense', $expense->id);
        $this->assertDatabaseMissing('vehicle_expenses', ['id' => $expense->id]);
        Carbon::setTestNow();
    }

    public function test_expenses_never_use_another_users_vehicle_or_category(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        DefaultVehicleExpenseCategories::createFor($owner);
        DefaultVehicleExpenseCategories::createFor($other);
        $vehicle = $owner->vehicles()->create(['name' => 'Propio', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina']);
        $foreignVehicle = $other->vehicles()->create(['name' => 'Ajeno', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina']);
        $foreignCategory = $other->vehicleExpenseCategories()->firstOrFail();

        $this->actingAs($owner);
        $this->get(route('vehicles.expenses', $foreignVehicle))->assertNotFound();

        Livewire::test(VehicleExpenses::class, ['vehicle' => $vehicle->id])
            ->call('openExpenseForm')
            ->set('expenseCategoryId', $foreignCategory->id)
            ->set('expenseAmount', 1000)
            ->call('saveExpense')
            ->assertHasErrors(['expenseCategoryId' => 'exists'])
            ->assertViewHas('categoryOptions', fn ($options) => ! array_key_exists($foreignCategory->id, $options));

        $this->expectException(QueryException::class);
        VehicleExpense::create(['vehicle_id' => $vehicle->id, 'vehicle_expense_category_id' => $foreignCategory->id, 'spent_on' => '2026-09-01', 'amount' => 1]);
    }

    public function test_categories_can_be_added_renamed_and_deleted_reassigning_their_expenses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DefaultVehicleExpenseCategories::createFor($user);
        DefaultVehicleExpenseCategories::createFor($other);
        $this->actingAs($user);
        $vehicle = $user->vehicles()->create(['name' => 'Cruze', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina']);
        $secondVehicle = $user->vehicles()->create(['name' => 'Moto', 'vehicle_type' => 'motocicleta', 'power_source' => 'gasolina']);
        $insurance = VehicleExpenseCategory::where('name', 'Seguros')->firstOrFail();
        $others = VehicleExpenseCategory::where('name', 'Otros')->firstOrFail();
        $vehicle->expenses()->create(['vehicle_expense_category_id' => $insurance->id, 'spent_on' => '2026-09-01', 'amount' => 77300]);
        $secondVehicle->expenses()->create(['vehicle_expense_category_id' => $insurance->id, 'spent_on' => '2026-09-02', 'amount' => 50000]);

        $component = Livewire::test(VehicleExpenses::class, ['vehicle' => $vehicle->id])
            ->call('openCategoryManager')
            ->assertSet('showCategoryManager', true)
            ->set('newCategoryName', 'seguros')
            ->call('addCategory')
            ->assertHasErrors(['newCategoryName'])
            ->set('newCategoryName', '  Lavado  ')
            ->call('addCategory')
            ->assertHasNoErrors()
            ->assertSet('newCategoryName', '');
        $washing = VehicleExpenseCategory::where('name', 'Lavado')->firstOrFail();

        $component->call('startCategoryRename', $washing->id)
            ->set('renamingCategoryName', 'Lavado y detallado')
            ->call('saveCategoryRename')
            ->assertHasNoErrors()
            ->assertSet('renamingCategoryId', null);
        $this->assertDatabaseHas('vehicle_expense_categories', ['id' => $washing->id, 'name' => 'Lavado y detallado']);

        // Sin gastos se elimina directamente.
        $component->call('deleteCategory', $washing->id);
        $this->assertDatabaseMissing('vehicle_expense_categories', ['id' => $washing->id]);

        // Con gastos (en cualquiera de los vehículos) se pide reasignar.
        $component->call('deleteCategory', $insurance->id)
            ->assertSet('deletingCategoryId', $insurance->id)
            ->assertSee('tiene 2 gastos')
            ->call('reassignAndDeleteCategory')
            ->assertHasErrors(['reassignCategoryTo' => 'required'])
            ->set('reassignCategoryTo', $other->vehicleExpenseCategories()->withoutGlobalScopes()->first()->id)
            ->call('reassignAndDeleteCategory')
            ->assertHasErrors(['reassignCategoryTo'])
            ->set('reassignCategoryTo', $others->id)
            ->call('reassignAndDeleteCategory')
            ->assertHasNoErrors()
            ->assertSet('deletingCategoryId', null);

        $this->assertDatabaseMissing('vehicle_expense_categories', ['id' => $insurance->id]);
        $this->assertSame(2, VehicleExpense::where('vehicle_expense_category_id', $others->id)->count());

        $component->call('restoreDefaultCategories');
        $this->assertTrue(VehicleExpenseCategory::where('name', 'Seguros')->exists());
        $this->assertSame(count(DefaultVehicleExpenseCategories::all()), $other->vehicleExpenseCategories()->withoutGlobalScopes()->count());
    }
}
