<?php

namespace Tests\Feature;

use App\Livewire\Learning\LearningPage;
use App\Models\LearningFormation;
use App\Models\LearningUnit;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Experience\PersonalExperience;
use App\Services\Learning\LearningRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LearningModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_require_login_and_reject_foreign_or_mismatched_context(): void
    {
        $this->get('/learning')->assertRedirect('/login');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(LearningRecords::class);
        $formation = $service->saveFormation($owner->id, null, ['title' => 'Degree', 'type' => 'degree', 'status' => 'in_progress']);
        $second = $service->saveFormation($owner->id, null, ['title' => 'Second', 'type' => 'degree', 'status' => 'pending']);
        $unit = $service->saveUnit($owner->id, $formation->id, null, null, 'subject', ['title' => 'Math', 'status' => 'pending']);
        $this->actingAs($other)->get(route('learning.show', $formation->id))->assertNotFound();
        $this->get(route('learning.units.show', [$formation->id, $unit->id]))->assertNotFound();
        $this->actingAs($owner)->get(route('learning.units.show', [$second->id, $unit->id]))->assertNotFound();
        $this->get('/learning')->assertOk()->assertSee('Degree');
    }

    public function test_index_detail_unit_and_categories_render_with_shell_and_no_future_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(LearningRecords::class);
        $formation = $service->saveFormation($user->id, null, ['title' => 'Carrera real', 'type' => 'degree', 'status' => 'in_progress']);
        $subject = $service->saveUnit($user->id, $formation->id, null, null, 'subject', ['title' => 'Matemáticas', 'status' => 'pending']);
        foreach ([route('learning'), route('learning.show', $formation->id), route('learning.units.show', [$formation->id, $subject->id]), route('categories.settings', 'learning')] as $url) {
            $this->get($url)->assertOk()->assertSee('data-module="learning"', false)->assertDontSee('Agregar reserva')->assertDontSee('Agregar evaluación');
        }
        $this->get(route('learning.show', $formation->id))->assertSee('Matemáticas')->assertSee('Accesos directos');
    }

    public function test_creating_editing_pinning_and_deleting_from_livewire_persists(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(LearningPage::class)->call('openFormation', null, 'degree')
            ->set('draft.title', 'Ingeniería')->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('Ingeniería');
        $formation = LearningFormation::firstOrFail();
        $page->call('openFormation', $formation->id)->set('draft.title', 'Ingeniería de sistemas')->call('save')->assertSee('Ingeniería de sistemas');
        $detail = Livewire::test(LearningPage::class, ['formation' => $formation->id])->call('openUnit', 'period')
            ->set('draft.title', 'Semestre 1')->call('save')->assertHasNoErrors()->assertSee('Semestre 1');
        $unit = LearningUnit::firstOrFail();
        $detail->call('setPin', 'unit', $unit->id, true)->assertSee('Acceso directo fijado.');
        Livewire::test(LearningPage::class)->assertSee('Semestre 1');
        $detail->call('deleteRecord', $formation->id)->assertHasErrors('deletion');
        $detail->call('deleteRecord', $formation->id, $unit->id)->assertHasNoErrors();
        $this->assertDatabaseCount('learning_shortcuts', 0);
        $page->call('deleteRecord', $formation->id)->assertHasNoErrors();
        $this->assertDatabaseCount('learning_formations', 0);
    }

    public function test_validation_cancel_filters_and_leaf_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = Livewire::test(LearningPage::class)->call('openFormation', null, 'course')->call('save')->assertHasErrors('draft.title')->assertSet('showForm', true)
            ->set('draft.title', 'Cancelled')->call('closeForm')->assertSet('draft', []);
        $this->assertDatabaseCount('learning_formations', 0);
        $service = app(LearningRecords::class);
        $course = $service->saveFormation($user->id, null, ['title' => 'Scrum', 'type' => 'course', 'status' => 'in_progress']);
        $service->saveFormation($user->id, null, ['title' => 'English', 'type' => 'personal', 'status' => 'pending']);
        $page->set('search', 'Scrum')->assertSee('Scrum')->assertDontSee('English');
        $page->call('clearFilters')->set('typeFilter', 'personal')->assertSee('English')->assertDontSee('Scrum');
        $page->set('typeFilter', 'course')->set('statusFilter', 'pending')->assertSee('No hay formaciones con estos filtros.');
        $module = $service->saveUnit($user->id, $course->id, null, null, 'module', ['title' => 'Module', 'status' => 'pending']);
        $topic = $service->saveUnit($user->id, $course->id, null, $module->id, 'topic', ['title' => 'Topic', 'status' => 'pending']);
        $this->get(route('learning.units.show', [$course->id, $topic->id]))->assertOk()->assertDontSee('Agregar contenido');
    }

    public function test_learning_is_optional_and_existing_hidden_modules_stay_hidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ModuleSetting::create(['user_id' => $user->id, 'module' => PersonalExperience::KEY, 'settings' => ['modules' => ['habits', 'goals']]]);
        $experience = app(PersonalExperience::class);
        $this->assertArrayHasKey('learning', $experience->catalog());
        $this->assertFalse($experience->visible('learning', $user->id));
        $this->assertFalse($experience->visible('water', $user->id));
        $experience->saveModules($user->id, ['habits', 'goals', 'learning']);
        $this->assertTrue($experience->visible('learning', $user->id));
        $this->assertFalse($experience->visible('water', $user->id));
    }
}
