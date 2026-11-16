<?php

namespace Tests\Feature;

use App\Actions\ManageTaskCategories;
use App\Actions\ManageTaskStatuses;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskSettings;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_tab_renders_the_users_categories(): void
    {
        $user = User::factory()->create();
        DefaultTaskCategories::createFor($user);

        $this->actingAs($user)->get(route('tasks.settings'))
            ->assertOk()
            ->assertSee('Categorías')
            ->assertSee('Tecnología');
    }

    public function test_user_can_create_rename_and_see_category_in_task_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskSettings::class)
            ->call('openForm')
            ->set('name', 'Siigo')
            ->set('icon', 'bi-briefcase')
            ->call('save')
            ->assertHasNoErrors();

        $category = TaskCategory::firstOrFail();
        $this->assertSame('siigo', $category->key);

        Livewire::test(TaskSettings::class)
            ->call('openForm', $category->id)
            ->set('name', 'Siigo Nube')
            ->call('save');

        $this->assertSame('siigo', $category->fresh()->key);
        Livewire::test(TaskList::class)->assertSet('categories', ['siigo' => 'Siigo Nube']);
    }

    public function test_deleting_a_used_category_asks_where_to_move_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DefaultTaskCategories::createFor($user);
        $task = Task::create(['title' => 'Barrer', 'category' => 'hogar']);
        $hogar = TaskCategory::where('key', 'hogar')->firstOrFail();

        Livewire::test(TaskSettings::class)
            ->call('delete', $hogar->id)
            ->assertSet('deletingId', $hogar->id)
            ->call('reassignAndDelete')
            ->assertSet('deletingId', null);

        $this->assertNull($task->fresh()->category);
        $this->assertModelMissing($hogar);
    }

    public function test_categories_can_be_reordered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DefaultTaskCategories::createFor($user);
        $second = TaskCategory::orderBy('sort_order')->skip(1)->firstOrFail();

        Livewire::test(TaskSettings::class)->call('move', $second->id, -1);

        $this->assertSame($second->key, array_key_first(TaskCategory::options()));
    }

    public function test_settings_never_offer_restoring_default_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('tasks.settings'))
            ->assertOk()
            ->assertDontSee('Restaurar predeterminadas')
            ->assertDontSee('Agregar predeterminadas');

        DefaultTaskCategories::createFor($user);

        $this->get(route('tasks.settings'))
            ->assertOk()
            ->assertDontSee('Restaurar predeterminadas')
            ->assertDontSee('Agregar predeterminadas');
    }

    public function test_restore_defaults_is_not_available_and_creates_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        try {
            Livewire::test(TaskSettings::class)->call('restoreDefaults');
            $this->fail('La acción de restaurar predeterminadas sigue disponible.');
        } catch (\Throwable) {
            $this->assertSame(0, TaskCategory::count());
        }
    }

    public function test_category_catalog_searches_by_name_and_visible_path(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($user, 'Trabajo');
        $manager->create($user, 'Proyecto', null, $root->id);
        $manager->create($user, 'Hogar');

        Livewire::test(TaskSettings::class)
            ->set('q', 'proyecto')
            ->assertSee('Trabajo / Proyecto')
            ->assertDontSee('Hogar')
            ->set('q', 'trabajo')
            ->assertSee('Trabajo / Proyecto')
            ->set('q', '')
            ->assertSee('Hogar');
    }

    public function test_status_catalog_searches_by_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        TaskStatus::ensureFor($user->id);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->set('q', 'marcha')
            ->assertSee('En marcha')
            ->assertDontSee('Terminada')
            ->set('q', '')
            ->assertSee('Terminada');
    }

    public function test_catalogs_use_twenty_five_rows_by_default_and_accept_the_canonical_sizes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskSettings::class)->assertSet('perPage', 25);

        foreach ([10, 25, 50, 100] as $size) {
            Livewire::test(TaskSettings::class)->set('perPage', $size)->assertSet('perPage', $size);
        }

        Livewire::test(TaskSettings::class)->set('perPage', 3)->assertSet('perPage', 25);
    }

    public function test_search_resets_the_page_and_pagination_limits_visible_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);

        for ($i = 1; $i <= 30; $i++) {
            $manager->create($user, sprintf('Categoria %02d', $i));
        }

        $component = Livewire::test(TaskSettings::class)
            ->assertSee('Categoria 01')
            ->assertDontSee('Categoria 30');

        $component->call('gotoPage', 2)
            ->assertSee('Categoria 30')
            ->assertDontSee('Categoria 01');

        $component->set('q', 'Categoria 01')
            ->assertSet('paginators.page', 1)
            ->assertSee('Categoria 01')
            ->assertDontSee('Categoria 02');
    }

    public function test_page_out_of_range_is_normalized_after_deleting_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $last = null;

        for ($i = 1; $i <= 26; $i++) {
            $last = $manager->create($user, sprintf('Categoria %02d', $i));
        }

        Livewire::test(TaskSettings::class)
            ->call('gotoPage', 2)
            ->assertSee('Categoria 26')
            ->call('delete', $last->id)
            ->assertSee('Categoria 01');
    }

    public function test_row_counters_ignore_recurrence_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Trabajo');
        Task::create(['title' => 'Pendiente', 'category' => $category->key]);
        Task::create(['title' => 'Hecha', 'category' => $category->key, 'completed' => true]);
        Task::create(['title' => 'Historial', 'category' => $category->key, 'is_recurrence_history' => true]);

        Livewire::test(TaskSettings::class)
            ->assertSeeHtml('data-label="Pendientes" class="md-table__nowrap">1<')
            ->assertSeeHtml('data-label="Total" class="md-table__nowrap">2<');
    }

    public function test_status_counters_ignore_recurrence_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $status = TaskStatus::firstOfType($user->id, TaskStatus::TODO);
        Task::create(['title' => 'Viva', 'status_id' => $status->id]);
        Task::create(['title' => 'Historial', 'status_id' => $status->id, 'is_recurrence_history' => true]);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertSeeHtml('data-label="Tareas" class="md-table__nowrap">1<');
    }

    public function test_catalog_preserves_hierarchical_and_type_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($user, 'Alfa');
        $manager->create($user, 'Hija', null, $root->id);
        $manager->create($user, 'Zeta');

        Livewire::test(TaskSettings::class)->assertSeeHtmlInOrder(['Alfa', 'Hija', 'Zeta']);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertSeeHtmlInOrder(['Pendiente', 'Preparada', 'En marcha', 'Terminada']);
    }

    public function test_catalogs_and_counters_are_isolated_between_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner);
        app(ManageTaskCategories::class)->create($owner, 'Solo mía');
        TaskStatus::ensureFor($owner->id);
        $foreignCategory = app(ManageTaskCategories::class)->create($other, 'Ajena');
        $foreignStatus = app(ManageTaskStatuses::class)->create($other, 'Estado ajeno', TaskStatus::READY);

        Livewire::test(TaskSettings::class)
            ->assertSee('Solo mía')
            ->assertDontSee('Ajena')
            ->call('delete', $foreignCategory->id);
        $this->assertDatabaseHas('task_categories', ['id' => $foreignCategory->id, 'name' => 'Ajena']);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertDontSee('Estado ajeno')
            ->call('moveStatus', $foreignStatus->id, -1);
        $this->assertDatabaseHas('task_statuses', ['id' => $foreignStatus->id, 'name' => 'Estado ajeno']);
    }

    public function test_move_uses_real_neighbors_hidden_by_search(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $alfa = $manager->create($user, 'Alfa');
        $bravo = $manager->create($user, 'Bravo');
        $charlie = $manager->create($user, 'Charlie');

        Livewire::test(TaskSettings::class)
            ->assertDontSeeHtml("move('{$alfa->id}', -1)")
            ->assertSeeHtml("move('{$bravo->id}', -1)")
            ->assertSeeHtml("move('{$bravo->id}', 1)")
            ->assertDontSeeHtml("move('{$charlie->id}', 1)");

        // Con la búsqueda de Bravo sus vecinos quedan ocultos y aun así conserva ambos movimientos.
        Livewire::test(TaskSettings::class)
            ->set('q', 'Bravo')
            ->assertSeeHtml("move('{$bravo->id}', -1)")
            ->assertSeeHtml("move('{$bravo->id}', 1)")
            ->assertDontSee('Alfa')
            ->assertDontSee('Charlie');

        Livewire::test(TaskSettings::class)->call('move', $bravo->id, -1);
        $this->assertSame(['bravo', 'alfa', 'charlie'], array_keys(TaskCategory::options()));
    }

    public function test_move_flags_consider_the_full_catalog_across_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $last = null;

        for ($i = 1; $i <= 26; $i++) {
            $last = $manager->create($user, sprintf('Categoria %02d', $i));
        }

        Livewire::test(TaskSettings::class)
            ->call('gotoPage', 2)
            ->assertSee('Categoria 26')
            ->assertSeeHtml("move('{$last->id}', -1)")
            ->assertDontSeeHtml("move('{$last->id}', 1)");
    }

    public function test_child_categories_move_within_their_branch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $rootA = $manager->create($user, 'Raíz A');
        $child1 = $manager->create($user, 'Hija 1', null, $rootA->id);
        $child2 = $manager->create($user, 'Hija 2', null, $rootA->id);
        $rootB = $manager->create($user, 'Raíz B');

        Livewire::test(TaskSettings::class)
            ->assertDontSeeHtml("move('{$rootA->id}', -1)")
            ->assertSeeHtml("move('{$rootA->id}', 1)")
            ->assertDontSeeHtml("move('{$rootB->id}', 1)")
            ->assertDontSeeHtml("move('{$child1->id}', -1)")
            ->assertSeeHtml("move('{$child1->id}', 1)")
            ->assertDontSeeHtml("move('{$child2->id}', 1)");
    }

    public function test_status_move_stays_within_type_and_respects_real_limits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $statuses = TaskStatus::ensureFor($user->id);
        $todo = $statuses->where('type', TaskStatus::TODO)->first();
        $ready = $statuses->where('type', TaskStatus::READY)->first();
        $second = app(ManageTaskStatuses::class)->create($user, 'Segundo', TaskStatus::TODO);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertDontSeeHtml("moveStatus('{$todo->id}', -1)")
            ->assertSeeHtml("moveStatus('{$todo->id}', 1)")
            ->assertSeeHtml("moveStatus('{$second->id}', -1)")
            ->assertDontSeeHtml("moveStatus('{$second->id}', 1)")
            ->assertDontSeeHtml("moveStatus('{$ready->id}', -1)")
            ->assertDontSeeHtml("moveStatus('{$ready->id}', 1)");
    }

    public function test_protected_statuses_do_not_offer_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $statuses = TaskStatus::ensureFor($user->id);
        $todo = $statuses->where('type', TaskStatus::TODO)->first();
        $done = $statuses->where('type', TaskStatus::DONE)->first();
        $wip = $statuses->where('type', TaskStatus::IN_PROGRESS)->first();

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertDontSeeHtml("deleteStatus('{$todo->id}')")
            ->assertDontSeeHtml("deleteStatus('{$done->id}')")
            ->assertSeeHtml("deleteStatus('{$wip->id}')");
    }

    public function test_both_sections_render_the_management_card_and_their_creation_dialogs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        TaskStatus::ensureFor($user->id);

        Livewire::test(TaskSettings::class)
            ->assertSee('Categorías de tareas')
            ->assertSeeHtml('id="task-settings-categories"')
            ->assertSeeHtml('md-create-fab')
            ->assertDontSeeHtml('id="task-category-dialog"')
            ->call('openForm')
            ->assertSet('showForm', true)
            ->assertSeeHtml('id="task-category-dialog"')
            ->call('closeForm')
            ->assertSet('showForm', false);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->assertSee('Estados de tareas')
            ->assertSeeHtml('id="task-settings-statuses"')
            ->assertSeeHtml('md-create-fab')
            ->assertDontSeeHtml('id="task-status-dialog"')
            ->call('openForm')
            ->assertSet('showStatusForm', true)
            ->assertSeeHtml('id="task-status-dialog"')
            ->call('closeStatusForm')
            ->assertSet('showStatusForm', false);
    }

    public function test_editing_and_reassignment_dialogs_open_in_both_sections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Trabajo');
        Task::create(['title' => 'Pendiente', 'category' => $category->key]);
        $status = TaskStatus::firstOfType($user->id, TaskStatus::READY);
        Task::create(['title' => 'Refinar', 'status_id' => $status->id]);

        Livewire::test(TaskSettings::class)
            ->call('openForm', $category->id)
            ->assertSet('editingId', $category->id)
            ->assertSet('name', 'Trabajo')
            ->call('closeForm')
            ->assertSet('editingId', null)
            ->call('delete', $category->id)
            ->assertSet('deletingId', $category->id)
            ->assertSee('Pasar tareas a')
            ->call('cancelDelete')
            ->assertSet('deletingId', null);

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->call('openStatusForm', $status->id)
            ->assertSet('editingStatusId', $status->id)
            ->assertSet('statusName', 'Preparada')
            ->call('closeStatusForm')
            ->assertSet('editingStatusId', null)
            ->call('deleteStatus', $status->id)
            ->assertSet('deletingStatusId', $status->id)
            ->assertSee('Pasar tareas a')
            ->call('cancelStatusDelete')
            ->assertSet('deletingStatusId', null);
    }

    public function test_empty_category_catalog_points_to_the_fab_without_extra_create_buttons(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskSettings::class)
            ->assertSee('Aún no tienes categorías')
            ->assertSeeHtml('md-create-fab')
            ->assertDontSee('Agregar predeterminadas')
            ->assertDontSee('Restaurar predeterminadas');
    }
}
