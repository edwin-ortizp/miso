<?php

namespace Tests\Feature;

use App\Actions\ManageTaskStatuses;
use App\Livewire\Task\TaskSettings;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Task\CompleteTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\Task\ManageTaskStatusTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskGamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TaskStatusesTest extends TestCase
{
    use RefreshDatabase;

    private function statusOf(User $user, string $type): TaskStatus
    {
        return TaskStatus::firstOfType($user->id, $type);
    }

    public function test_new_tasks_get_default_catalog_and_draft_status(): void
    {
        $user = User::factory()->create();
        $task = $user->tasks()->create(['title' => 'Escribir prompt']);

        $this->assertSame(['Pendiente', 'Preparada', 'En marcha', 'Terminada'], TaskStatus::ensureFor($user->id)->pluck('name')->all());
        $this->assertSame(TaskStatus::TODO, $task->fresh()->status->type);
    }

    public function test_completed_flag_and_done_status_stay_in_sync(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Revisar']);

        $task->update(['status_id' => $this->statusOf($user, TaskStatus::READY)->id]);
        $this->assertFalse($task->fresh()->completed);

        app(TaskGamificationService::class)->complete($task->fresh());
        $task = $task->fresh();
        $this->assertTrue($task->completed);
        $this->assertSame(TaskStatus::DONE, $task->status->type);

        app(TaskGamificationService::class)->reopen($task);
        $this->assertSame(TaskStatus::TODO, $task->fresh()->status->type);

        $task->update(['status_id' => $this->statusOf($user, TaskStatus::DONE)->id]);
        $this->assertTrue($task->fresh()->completed);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_status_ids_of_other_users_are_ignored(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = $owner->tasks()->create(['title' => 'Mía']);
        $original = $task->status_id;

        $task->update(['status_id' => $this->statusOf($other, TaskStatus::READY)->id]);

        $this->assertSame($original, $task->fresh()->status_id);
    }

    public function test_catalog_rules_allow_many_of_each_type_but_one_done(): void
    {
        $user = User::factory()->create();
        $actions = app(ManageTaskStatuses::class);

        $actions->create($user, 'Idea', TaskStatus::TODO);
        $actions->create($user, 'Bloqueada', TaskStatus::IN_PROGRESS);
        $this->assertSame(2, TaskStatus::where('user_id', $user->id)->where('type', TaskStatus::TODO)->count());

        $this->expectException(InvalidArgumentException::class);
        $actions->create($user, 'Cerrada', TaskStatus::DONE);
    }

    public function test_done_and_last_todo_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $actions = app(ManageTaskStatuses::class);

        foreach ([TaskStatus::DONE, TaskStatus::TODO] as $type) {
            try {
                $actions->delete($this->statusOf($user, $type));
                $this->fail("Se eliminó el estado {$type}.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_settings_statuses_tab_creates_and_deletes_with_reassignment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Refinar', 'status_id' => $this->statusOf($user, TaskStatus::READY)->id]);

        $this->get(route('tasks.settings.statuses'))->assertOk()->assertSee('Estados')->assertSee('Preparada')->assertSee('Categorías');

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->call('openForm')
            ->set('statusName', 'Revisión')
            ->set('statusType', TaskStatus::READY)
            ->call('saveStatus')
            ->assertHasNoErrors();

        $review = TaskStatus::where('name', 'Revisión')->firstOrFail();

        Livewire::test(TaskSettings::class, ['section' => 'statuses'])
            ->call('deleteStatus', $this->statusOf($user, TaskStatus::READY)->id)
            ->assertSet('deletingStatusId', fn ($id) => $id !== null)
            ->set('statusReassignTo', $review->id)
            ->call('reassignAndDeleteStatus')
            ->assertHasNoErrors();

        $this->assertSame($review->id, $task->fresh()->status_id);
    }

    public function test_mcp_lists_filters_and_updates_by_status(): void
    {
        $user = User::factory()->create();
        $draft = $user->tasks()->create(['title' => 'Borrador de prompt']);
        $user->tasks()->create(['title' => 'Otra']);

        LifeTrackerServer::actingAs($user)
            ->tool(UpdateTaskTool::class, ['task_id' => $draft->id, 'status' => 'ready'])
            ->assertOk()
            ->assertSee('estado → Preparada');

        LifeTrackerServer::actingAs($user)
            ->tool(ListTasksTool::class, ['status_type' => ['ready']])
            ->assertOk()
            ->assertSee('Borrador de prompt')
            ->assertDontSee('Otra');

        LifeTrackerServer::actingAs($user)
            ->tool(GetTaskTool::class, ['task_id' => $draft->id])
            ->assertSee('"type":"ready"');

        LifeTrackerServer::actingAs($user)
            ->tool(UpdateTaskTool::class, ['task_id' => $draft->id, 'status' => 'Terminada'])
            ->assertHasErrors(['complete-task-tool']);

        LifeTrackerServer::actingAs($user)
            ->tool(CompleteTaskTool::class, ['task_id' => $draft->id, 'action' => 'complete'])
            ->assertOk();
        $this->assertSame(TaskStatus::DONE, $draft->fresh()->status->type);
    }

    public function test_mcp_manages_the_status_catalog(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(ManageTaskStatusTool::class, ['action' => 'create', 'name' => 'Esperando', 'type' => 'in_progress'])
            ->assertOk()
            ->assertSee('Esperando');

        LifeTrackerServer::actingAs($user)
            ->tool(ManageTaskStatusTool::class, ['action' => 'create', 'name' => 'Cerrada', 'type' => 'done'])
            ->assertHasErrors();

        LifeTrackerServer::actingAs($user)
            ->tool(ManageTaskStatusTool::class, ['action' => 'list'])
            ->assertOk()
            ->assertSee(['Pendiente', 'Esperando', 'in_progress']);
    }
}
