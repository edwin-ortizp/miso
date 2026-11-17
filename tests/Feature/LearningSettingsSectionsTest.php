<?php

namespace Tests\Feature;

use App\Livewire\Learning\LearningPage;
use App\Livewire\Learning\LearningSettings;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\Learning\LearningRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-147: detalle de Formación sin migas ni elipsis suelta, y ajustes con categorías por defecto. */
class LearningSettingsSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_get_default_learning_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $names = UserCategory::where('domain', 'learning')->pluck('name')->all();

        foreach (['Tecnología', 'Idiomas', 'Ciencias', 'Humanidades', 'Salud', 'Negocios', 'Arte', 'Otro'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_settings_have_a_side_nav_with_vocabulary_and_categories(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('learning.settings'))->assertOk()->assertSee('Secciones de Ajustes de Formación')->assertSee('Vocabulario')->assertSee('Categorías');
        $this->get(route('learning.settings.categories'))->assertOk()->assertSee('Categorías · Formación')->assertSee('Tecnología')->assertSee('Crear categoría');
    }

    public function test_categories_section_creates_and_blocks_deleting_used_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(LearningSettings::class, ['section' => 'categories'])
            ->call('openCategoryForm')->set('categoryName', 'Matemáticas')->call('saveCategory')->assertHasNoErrors()->assertSee('Matemáticas');
        $this->assertDatabaseHas('user_categories', ['user_id' => $user->id, 'domain' => 'learning', 'name' => 'Matemáticas']);

        $used = UserCategory::where('domain', 'learning')->where('key', 'technology')->firstOrFail();
        app(LearningRecords::class)->saveFormation($user->id, null, ['title' => 'Curso', 'type' => 'course', 'status' => 'pending', 'category' => 'technology']);

        Livewire::test(LearningSettings::class, ['section' => 'categories'])->call('deleteCategory', $used->id);
        $this->assertDatabaseHas('user_categories', ['id' => $used->id]);
    }

    public function test_unknown_settings_section_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(LearningSettings::class, ['section' => 'nope'])->assertStatus(404);
    }

    public function test_detail_has_no_breadcrumbs_and_offers_add_actions_in_the_menus(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $formation = app(LearningRecords::class)->saveFormation($user->id, null, ['title' => 'Ingeniería de sistemas', 'type' => 'degree', 'status' => 'in_progress', 'institution' => 'UNAL']);

        $this->get(route('learning.show', $formation->id))->assertOk()
            ->assertDontSee('learning-path', false)
            ->assertDontSee('botón flotante')
            ->assertDontSee(route('categories.settings', 'learning'), false)
            ->assertSee('UNAL')
            ->assertSee('Agregar semestre');

        Livewire::test(LearningPage::class, ['formation' => $formation->id])->assertSee('Agregar semestre');
    }
}
