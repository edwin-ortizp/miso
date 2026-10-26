<?php

namespace Tests\Feature;

use App\Actions\ManageTaskCategories;
use App\Actions\ManageTaskStatuses;
use App\Livewire\Task\TaskKanban;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskPlanning;
use App\Livewire\Task\TaskSettings;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCatalogColorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_color_is_created_loaded_changed_and_reset(): void
    {
        $this->actingAs(User::factory()->create());
        $form = Livewire::test(TaskSettings::class)->call('openForm')
            ->set('name', 'Proyecto')->set('color', 'violet')->call('save')->assertHasNoErrors();
        $category = TaskCategory::firstOrFail();
        $this->assertSame('violet', $category->color);
        $form->call('openForm', $category->id)->assertSet('color', 'violet')
            ->set('color', 'teal')->call('save')->assertHasNoErrors();
        $this->assertSame('teal', $category->fresh()->color);
        $form->call('openForm', $category->id)->set('color', '')->call('save')->assertHasNoErrors();
        $this->assertNull($category->fresh()->color);
        $form->call('openForm')->assertSet('color', '');
    }

    public function test_status_color_including_done_is_editable_without_changing_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $form = Livewire::test(TaskSettings::class, ['section' => 'statuses'])->call('openStatusForm')
            ->set('statusName', 'Revisión')->set('statusColor', 'blue')->call('saveStatus')->assertHasNoErrors();
        $status = TaskStatus::where('name', 'Revisión')->firstOrFail();
        $this->assertSame('blue', $status->color);
        $form->call('openStatusForm', $status->id)->assertSet('statusColor', 'blue')
            ->set('statusColor', 'orange')->call('saveStatus')->assertHasNoErrors();
        $this->assertSame('orange', $status->fresh()->color);
        $done = TaskStatus::firstOfType($user->id, TaskStatus::DONE);
        $form->call('openStatusForm', $done->id)->set('statusColor', 'pink')->call('saveStatus')->assertHasNoErrors();
        $this->assertSame('pink', $done->fresh()->color);
        $this->assertSame(TaskStatus::DONE, $done->fresh()->type);
        $form->call('openStatusForm', $done->id)->set('statusColor', '')->call('saveStatus')->assertHasNoErrors();
        $this->assertNull($done->fresh()->color);
    }

    public function test_invalid_palette_keys_are_rejected_in_both_forms_and_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(TaskSettings::class)->call('openForm')->set('name', 'Inválida')
            ->set('color', '#123456')->call('save')->assertHasErrors('color');
        Livewire::test(TaskSettings::class, ['section' => 'statuses'])->call('openStatusForm')
            ->set('statusName', 'Inválido')->set('statusColor', 'url(bad)')->call('saveStatus')->assertHasErrors('statusColor');
        foreach ([
            fn () => app(ManageTaskCategories::class)->create($user, 'Inválida', color: 'invalid'),
            fn () => app(ManageTaskStatuses::class)->create($user, 'Inválido', TaskStatus::TODO, 'invalid'),
        ] as $action) {
            try {
                $action();
                $this->fail('Invalid color accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('color', $e->getMessage());
            }
        }
    }

    public function test_legacy_action_calls_preserve_color_and_explicit_null_resets_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $categories = app(ManageTaskCategories::class);
        $statuses = app(ManageTaskStatuses::class);
        $category = $categories->create($user, 'Proyecto', color: 'red');
        $status = $statuses->create($user, 'Revisión', TaskStatus::TODO, 'green');
        $categories->update($category, 'Renombrada');
        $statuses->update($status, 'Renombrado');
        $this->assertSame('red', $category->fresh()->color);
        $this->assertSame('green', $status->fresh()->color);
        $categories->update($category, 'Renombrada', color: null);
        $statuses->update($status, 'Renombrado', color: null);
        $this->assertNull($category->fresh()->color);
        $this->assertNull($status->fresh()->color);
    }

    public function test_colors_are_rendered_across_task_views_and_refreshed_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Proyecto', color: 'violet');
        $task = Task::create(['title' => 'Colores visibles', 'category' => $category->key]);
        $task->status->update(['color' => 'orange']);
        foreach ([TaskList::class, TaskPlanning::class, TaskKanban::class] as $view) {
            Livewire::test($view)->assertSeeHtml('md-catalog-chip--violet');
        }
        $list = Livewire::test(TaskList::class)->assertSeeHtml('md-catalog-chip--orange');
        $category->update(['color' => 'teal']);
        $list->call('$refresh')->assertSeeHtml('md-catalog-chip--teal')->assertDontSeeHtml('md-catalog-chip--violet');
    }

    public function test_foreign_colors_and_edits_are_isolated_by_user(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $category = app(ManageTaskCategories::class)->create($owner, 'Proyecto', color: 'pink');
        $status = app(ManageTaskStatuses::class)->create($owner, 'Privado', TaskStatus::TODO, 'pink');
        $this->actingAs(User::factory()->create());
        Livewire::test(TaskSettings::class)->set('editingId', $category->id)->set('name', 'Intento')
            ->set('color', 'red')->call('save');
        Livewire::test(TaskSettings::class, ['section' => 'statuses'])->set('editingStatusId', $status->id)
            ->set('statusName', 'Intento')->set('statusColor', 'red')->call('saveStatus');
        $this->assertSame('pink', $category->fresh()->color);
        $this->assertSame('pink', $status->fresh()->color);
        Livewire::test(TaskList::class)->assertDontSeeHtml('md-catalog-chip--pink');
    }

    public function test_uncolored_existing_records_keep_their_fallback_tones(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Sin color');
        $task = Task::create(['title' => 'Sin personalizar', 'category' => $category->key]);
        $this->assertNull($category->color);
        $this->assertNull($task->status->color);
        Livewire::test(TaskList::class)->assertSeeHtml('md-chip-tonal--primary')->assertDontSeeHtml('md-catalog-chip--blue');
    }
}
