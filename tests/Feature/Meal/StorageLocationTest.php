<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealIngredients;
use App\Livewire\Meal\MealSettings;
use App\Models\ShoppingItem;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StorageLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function item(string $name, ?StorageLocation $location = null): ShoppingItem
    {
        return ShoppingItem::create(['name' => $name, 'base_unit' => 'unit', 'stock' => 1, 'status' => 'available', 'storage_location_id' => $location?->id]);
    }

    public function test_settings_route_lists_locations_with_product_counts(): void
    {
        $kitchen = StorageLocation::create(['name' => 'Cocina']);
        $fridge = StorageLocation::create(['name' => 'Nevera', 'parent_id' => $kitchen->id]);
        $this->item('Leche', $fridge);
        $this->item('Queso', $fridge);

        $this->get(route('meals.settings.locations'))->assertOk()->assertSee('Ubicaciones')->assertSee('Cocina › Nevera')->assertSee('2 productos');
    }

    public function test_create_child_and_reject_third_level_and_sibling_duplicates(): void
    {
        $component = Livewire::test(MealSettings::class, ['section' => 'locations']);
        $component->call('openForm')->set('name', 'Cocina')->call('save')->assertHasNoErrors();
        $kitchen = StorageLocation::where('name', 'Cocina')->firstOrFail();
        $component->call('openForm')->set('name', 'Nevera')->set('parent_id', $kitchen->id)->call('save')->assertHasNoErrors();
        $fridge = StorageLocation::where('name', 'Nevera')->firstOrFail();
        $this->assertSame($kitchen->id, $fridge->parent_id);

        $component->call('openForm')->set('name', 'Cajón')->set('parent_id', $fridge->id)->call('save')->assertHasErrors('parent_id');
        $component->call('openForm')->set('name', ' NEVERA ')->set('parent_id', $kitchen->id)->call('save')->assertHasErrors('name');

        $patio = StorageLocation::create(['name' => 'Patio']);
        $component->call('openForm')->set('name', 'Nevera')->set('parent_id', $patio->id)->call('save')->assertHasNoErrors();
        $this->assertSame(2, StorageLocation::where('normalized_name', 'nevera')->count());
    }

    public function test_locations_are_isolated_per_user(): void
    {
        $other = User::factory()->create();
        $foreign = (new StorageLocation(['name' => 'Ajena']))->forceFill(['user_id' => $other->id]);
        $foreign->save();

        $this->expectException(ValidationException::class);
        $this->item('Arroz', $foreign);
    }

    public function test_archiving_parent_cascades_keeps_assignments_and_restore_brings_parent_back(): void
    {
        $kitchen = StorageLocation::create(['name' => 'Cocina']);
        $pantry = StorageLocation::create(['name' => 'Alacena', 'parent_id' => $kitchen->id]);
        $rice = $this->item('Arroz', $pantry);

        $component = Livewire::test(MealSettings::class, ['section' => 'locations']);
        $component->call('archive', $kitchen->id);
        $this->assertTrue($kitchen->fresh()->isArchived());
        $this->assertTrue($pantry->fresh()->isArchived());
        $this->assertSame($pantry->id, $rice->fresh()->storage_location_id);
        $this->assertArrayNotHasKey($pantry->id, StorageLocation::options());

        $component->call('restore', $pantry->id);
        $this->assertFalse($pantry->fresh()->isArchived());
        $this->assertFalse($kitchen->fresh()->isArchived());
    }

    public function test_locations_cannot_be_deleted(): void
    {
        $location = StorageLocation::create(['name' => 'Patio']);
        Livewire::test(MealSettings::class, ['section' => 'locations'])->call('delete', $location->id)->assertStatus(404);
        $this->assertDatabaseHas('storage_locations', ['id' => $location->id]);
    }

    public function test_product_form_assigns_and_clears_location_and_rejects_archived_ones(): void
    {
        $storage = StorageLocation::create(['name' => 'Bodega']);
        $old = StorageLocation::create(['name' => 'Vieja']);
        $old->archive();
        $item = $this->item('Detergente');

        $component = Livewire::test(MealIngredients::class);
        $component->call('openForm', $item->id)->set('storageLocationId', $storage->id)->call('save')->assertHasNoErrors();
        $this->assertSame($storage->id, $item->fresh()->storage_location_id);

        $component->call('openForm', $item->id)->set('storageLocationId', '')->call('save')->assertHasNoErrors();
        $this->assertNull($item->fresh()->storage_location_id);

        $component->call('openForm', $item->id)->set('storageLocationId', $old->id)->call('save')->assertHasErrors('storageLocationId');
    }

    public function test_product_keeps_its_archived_location_when_edited(): void
    {
        $old = StorageLocation::create(['name' => 'Vieja']);
        $item = $this->item('Jabón', $old);
        $old->archive();

        Livewire::test(MealIngredients::class)->call('openForm', $item->id)->assertSet('storageLocationId', $old->id)->call('save')->assertHasNoErrors();
        $this->assertSame($old->id, $item->fresh()->storage_location_id);
    }

    public function test_filter_by_parent_includes_children_and_supports_without_location(): void
    {
        $kitchen = StorageLocation::create(['name' => 'Cocina']);
        $fridge = StorageLocation::create(['name' => 'Nevera', 'parent_id' => $kitchen->id]);
        $patio = StorageLocation::create(['name' => 'Patio']);
        $this->item('Leche', $fridge);
        $this->item('Sal', $kitchen);
        $this->item('Detergente', $patio);
        $this->item('Croquetas');

        Livewire::test(MealIngredients::class)->set('locationFilter', [$kitchen->id])
            ->assertSee('Leche')->assertSee('Sal')->assertDontSee('Detergente')->assertDontSee('Croquetas')->assertSee('Ubicación: Cocina')
            ->set('locationFilter', [StorageLocation::NONE])
            ->assertSee('Croquetas')->assertDontSee('Leche')->assertDontSee('Detergente')
            ->set('locationFilter', [$patio->id, StorageLocation::NONE])
            ->assertSee('Croquetas')->assertSee('Detergente')->assertDontSee('Leche')
            ->call('removeFilter', 'location', $patio->id)->assertSet('locationFilter', [StorageLocation::NONE]);
    }
}
