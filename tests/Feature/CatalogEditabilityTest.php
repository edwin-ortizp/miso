<?php

namespace Tests\Feature;

use App\Livewire\Category\CategorySettings;
use App\Livewire\Relationship\RelationshipIndex;
use App\Livewire\Water\WaterSettings;
use App\Models\Circle;
use App\Models\DrinkType;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\Catalogs\ModuleCatalogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-121 T8: lo que trae una cuenta nueva no es fijo; cada persona lo puede cambiar. */
class CatalogEditabilityTest extends TestCase
{
    use RefreshDatabase;

    private function newAccount(): User
    {
        $user = User::create(['name' => 'Persona', 'email' => fake()->unique()->safeEmail(), 'password' => 'password']);
        ModuleCatalogs::ensureAll($user->id);
        $this->actingAs($user);

        return $user;
    }

    public function test_default_circles_can_be_renamed_added_and_deleted(): void
    {
        $this->newAccount();
        $couple = Circle::where('name', 'Pareja')->firstOrFail();

        Livewire::test(RelationshipIndex::class)
            ->call('openCircleForm', $couple->id)->set('circleName', 'Mi compañía')->call('saveCircle')
            ->call('openCircleForm')->set('circleName', 'Vecinos')->call('saveCircle')
            ->call('deleteCircle', Circle::where('name', 'Trabajo')->firstOrFail()->id);

        $this->assertEqualsCanonicalizing(['Mi compañía', 'Familia', 'Amigos', 'Vecinos'], Circle::pluck('name')->all());
    }

    public function test_domain_catalogs_can_be_renamed_added_and_deleted(): void
    {
        $this->newAccount();
        $default = UserCategory::where('domain', 'exercise')->where('key', 'cardio')->firstOrFail();

        Livewire::test(CategorySettings::class, ['domain' => 'exercise'])
            ->call('openForm', $default->id)->set('name', 'Aeróbico')->call('save')
            ->call('openForm')->set('name', 'Movilidad')->call('save')
            ->call('delete', UserCategory::where('domain', 'exercise')->where('key', 'sports')->firstOrFail()->id);

        $names = UserCategory::where('domain', 'exercise')->pluck('name')->all();
        $this->assertContains('Aeróbico', $names);
        $this->assertContains('Movilidad', $names);
        $this->assertNotContains('Deportes', $names);
        $this->assertSame('cardio', $default->fresh()->key);
    }

    public function test_default_drinks_can_be_renamed_added_and_deleted(): void
    {
        $this->newAccount();
        $water = DrinkType::where('name', 'Agua')->firstOrFail();

        Livewire::test(WaterSettings::class)
            ->call('openForm', $water->id)->set('name', 'Agua natural')->call('save')
            ->call('openForm')->set('name', 'Infusión')->set('icon', '🍵')->set('hydrationFactor', '0.90')->call('save')
            ->call('delete', DrinkType::where('name', 'Cerveza')->firstOrFail()->id);

        $names = DrinkType::pluck('name')->all();
        $this->assertContains('Agua natural', $names);
        $this->assertContains('Infusión', $names);
        $this->assertNotContains('Cerveza', $names);
    }
}
