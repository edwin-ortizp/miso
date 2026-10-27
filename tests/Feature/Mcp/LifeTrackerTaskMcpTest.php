<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Task\CreateTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\ListTaskCategoriesTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\Task\ManageTaskCategoryTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifeTrackerTaskMcpTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCategories(): User
    {
        $user = User::factory()->create();
        DefaultTaskCategories::createFor($user);

        return $user;
    }

    private function taskFor(User $user, array $attributes = []): Task
    {
        return $user->tasks()->create(['title' => 'Revisar informe', ...$attributes]);
    }

    public function test_create_task_accepts_category_by_name_and_recurrence(): void
    {
        $user = $this->userWithCategories();

        LifeTrackerServer::actingAs($user)
            ->tool(CreateTaskTool::class, [
                'title' => 'Pagar arriendo',
                'category' => 'finanzas',
                'start_date' => '2026-10-05',
                'recurrence' => 'monthly',
            ])
            ->assertOk()
            ->assertSee('cada mes');

        $task = Task::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('finanzas', $task->category);
        $this->assertTrue($task->is_recurrent);
        $this->assertSame(['pattern' => 'monthly', 'frequency' => 1], $task->recurrence);
        $this->assertTrue($task->start_is_date);
    }

    public function test_create_task_rejects_unknown_category(): void
    {
        $user = $this->userWithCategories();

        LifeTrackerServer::actingAs($user)
            ->tool(CreateTaskTool::class, ['title' => 'X', 'category' => 'inexistente'])
            ->assertHasErrors(['no existe']);

        $this->assertSame(0, Task::withoutGlobalScopes()->count());
    }

    public function test_update_task_changes_fields_and_appends_markdown(): void
    {
        $user = $this->userWithCategories();
        $task = $this->taskFor($user, ['description' => '- [ ] Leer']);

        LifeTrackerServer::actingAs($user)
            ->tool(UpdateTaskTool::class, [
                'task_id' => $task->id,
                'category' => 'Educación',
                'end_date' => '2026-09-30 17:00',
                'append_description' => '- [x] Resumir',
                'recurrence' => 'weekly',
                'recurrence_interval' => 2,
            ])
            ->assertOk()
            ->assertSee('cada 2 semanas');

        $task->refresh();
        $this->assertSame('educacion', $task->category);
        $this->assertSame("- [ ] Leer\n\n- [x] Resumir", $task->description);
        $this->assertSame('2026-09-30 17:00', $task->end_date->format('Y-m-d H:i'));
        $this->assertFalse($task->end_is_date);
        $this->assertSame(['pattern' => 'weekly', 'frequency' => 2], $task->recurrence);
    }

    public function test_update_task_can_remove_recurrence_and_set_rrule(): void
    {
        $user = $this->userWithCategories();
        $task = $this->taskFor($user, ['is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1]]);

        LifeTrackerServer::actingAs($user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'rrule' => 'freq=weekly;byday=mo,th'])->assertOk();
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,TH', $task->fresh()->recurrence['rrule']);

        LifeTrackerServer::actingAs($user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'recurrence' => 'none'])
            ->assertOk()->assertSee('Ya no es recurrente');
        $this->assertFalse($task->fresh()->is_recurrent);
        $this->assertNull($task->fresh()->recurrence);
    }

    public function test_update_and_get_task_are_isolated_per_user(): void
    {
        $owner = $this->userWithCategories();
        $intruder = $this->userWithCategories();
        $task = $this->taskFor($owner);

        LifeTrackerServer::actingAs($intruder)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'title' => 'Hackeada'])->assertHasErrors();
        LifeTrackerServer::actingAs($intruder)->tool(GetTaskTool::class, ['task_id' => $task->id])->assertHasErrors();

        $this->assertSame('Revisar informe', $task->fresh()->title);
    }

    public function test_get_task_returns_markdown_and_suggested_next_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(15, 0));
        $user = $this->userWithCategories();
        $task = $this->taskFor($user, [
            'description' => "Notas\n- [x] Uno\n- [ ] Dos",
            'category' => 'hogar',
            'start_date' => '2026-09-17',
            'is_recurrent' => true,
            'recurrence' => ['pattern' => 'weekly', 'frequency' => 1],
        ]);

        LifeTrackerServer::actingAs($user)->tool(GetTaskTool::class, ['task_id' => $task->id])
            ->assertOk()
            ->assertSee('Hogar')
            ->assertSee('- [ ] Dos')
            ->assertSee('cada semana')
            ->assertSee('2026-11-03');
    }

    public function test_list_tasks_filters_uncategorized(): void
    {
        $user = $this->userWithCategories();
        $this->taskFor($user, ['title' => 'Con categoría', 'category' => 'hogar']);
        $this->taskFor($user, ['title' => 'Suelta']);

        LifeTrackerServer::actingAs($user)->tool(ListTasksTool::class, ['uncategorized' => true])
            ->assertOk()->assertSee('Suelta')->assertDontSee('Con categoría');
    }

    public function test_manage_task_categories_create_rename_and_delete_with_reassign(): void
    {
        $user = $this->userWithCategories();
        $task = $this->taskFor($user, ['category' => 'compras']);

        LifeTrackerServer::actingAs($user)->tool(ManageTaskCategoryTool::class, ['action' => 'create', 'name' => 'Trabajo Siigo'])
            ->assertOk()->assertSee('trabajo-siigo');
        LifeTrackerServer::actingAs($user)->tool(ManageTaskCategoryTool::class, ['action' => 'create', 'name' => 'trabajo siigo'])
            ->assertHasErrors(['Ya tienes']);
        LifeTrackerServer::actingAs($user)->tool(ManageTaskCategoryTool::class, ['action' => 'rename', 'category' => 'compras', 'name' => 'Mercado'])
            ->assertOk();
        LifeTrackerServer::actingAs($user)->tool(ListTaskCategoriesTool::class)->assertOk()->assertSee('Mercado');

        LifeTrackerServer::actingAs($user)->tool(ManageTaskCategoryTool::class, ['action' => 'delete', 'category' => 'Mercado', 'reassign_to' => 'hogar'])
            ->assertOk()->assertSee('1 tareas pasaron');

        $this->assertSame('hogar', $task->fresh()->category);
        $this->assertFalse(TaskCategory::withoutGlobalScopes()->where('user_id', $user->id)->where('key', 'compras')->exists());
    }

    public function test_update_task_adds_and_removes_external_refs_without_duplicates(): void
    {
        $user = $this->userWithCategories();
        $task = $this->taskFor($user);

        LifeTrackerServer::actingAs($user)
            ->tool(UpdateTaskTool::class, [
                'task_id' => $task->id,
                'add_external_refs' => [
                    ['provider' => 'Jira', 'type' => 'issue', 'id' => 'sgx-323'],
                    ['provider' => 'gesthor', 'type' => 'task', 'id' => 'G-1'],
                ],
            ])
            ->assertOk();

        LifeTrackerServer::actingAs($user)
            ->tool(UpdateTaskTool::class, [
                'task_id' => $task->id,
                'add_external_refs' => [['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-323', 'url' => 'https://jira/SGX-323']],
                'remove_external_refs' => [['provider' => 'gesthor', 'type' => 'task', 'id' => 'G-1']],
            ])
            ->assertOk();

        $this->assertSame(
            [['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-323', 'url' => 'https://jira/SGX-323']],
            $task->fresh()->external_refs,
        );
    }

    public function test_create_task_accepts_external_refs(): void
    {
        $user = $this->userWithCategories();

        LifeTrackerServer::actingAs($user)
            ->tool(CreateTaskTool::class, [
                'title' => 'Revisar listas de precios',
                'add_external_refs' => [['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-10']],
            ])
            ->assertOk();

        $task = Task::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame([['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-10']], $task->external_refs);
    }

    public function test_list_tasks_finds_exact_external_ref(): void
    {
        $user = $this->userWithCategories();
        $this->taskFor($user, ['title' => 'Listas de precios', 'external_refs' => [['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-323']]]);
        $this->taskFor($user, ['title' => 'Otra', 'external_refs' => [['provider' => 'jira', 'type' => 'issue', 'id' => 'SGX-3230']]]);

        LifeTrackerServer::actingAs($user)
            ->tool(ListTasksTool::class, ['external_ref' => 'sgx-323'])
            ->assertOk()
            ->assertSee('Listas de precios')
            ->assertDontSee('Otra');
    }

    public function test_list_tasks_search_includes_description(): void
    {
        $user = $this->userWithCategories();
        $this->taskFor($user, ['title' => 'Terminar diagrama', 'description' => 'BPMN del ciclo de factura']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListTasksTool::class, ['search' => 'ciclo de factura'])
            ->assertOk()
            ->assertSee('Terminar diagrama');
    }

    public function test_list_tasks_filters_by_updated_since(): void
    {
        $user = $this->userWithCategories();
        $old = $this->taskFor($user, ['title' => 'Tarea vieja']);
        $old->timestamps = false;
        $old->forceFill(['updated_at' => now()->subDays(10)])->save();
        $this->taskFor($user, ['title' => 'Tarea reciente']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListTasksTool::class, ['updated_since' => now()->subDay()->toDateString()])
            ->assertOk()
            ->assertSee('Tarea reciente')
            ->assertDontSee('Tarea vieja');
    }

    public function test_follow_up_lines_do_not_count_as_subtasks(): void
    {
        $user = $this->userWithCategories();
        $task = $this->taskFor($user, ['description' => "Contexto.\n\n## Pendientes\n\n- [x] Reunión\n- [ ] Ajustar alternativas\n\n## Seguimiento\n\n- 21 sep 2026 · 10:30 — Reunión realizada.\n- 20 sep 2026 — Primera versión."]);

        $this->assertSame(['completed' => 1, 'total' => 2], $task->subtask_progress);
    }
}
