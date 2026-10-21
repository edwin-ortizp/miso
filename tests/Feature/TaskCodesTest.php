<?php

namespace Tests\Feature;

use App\Actions\ManageTaskCategories;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskSettings;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Task\CompleteTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_codes_are_per_user_and_prefix_and_immutable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Siigo');
        $other = app(ManageTaskCategories::class)->create($user, 'Gesthor', parentId: $category->id);
        $first = Task::create(['title' => 'Uno', 'category' => $category->key]);
        $second = Task::create(['title' => 'Dos', 'category' => $category->key]);
        $this->assertSame('SGX-00001', $first->code);
        $this->assertSame('SGX-00002', $second->code);
        $this->assertSame('GES-00001', Task::create(['title' => 'Tres', 'category' => $other->key])->code);
        $this->assertSame('MSO-00001', Task::create(['title' => 'Sin categoría'])->code);
        $first->update(['category' => $other->key]);
        $category->update(['code_prefix' => 'SG']);
        $this->assertSame('SGX-00001', $first->fresh()->code);
        $this->assertSame('SG-00001', Task::create(['title' => 'Nuevo', 'category' => $category->key])->code);
        $other->update(['code_prefix' => 'SGX']);
        $this->assertSame('SGX-00003', Task::create(['title' => 'Reuso', 'category' => $other->key])->code);
        $first->delete();
        $this->assertSame('SGX-00004', Task::create(['title' => 'Sin reutilizar', 'category' => $other->key])->code);
        $first = $second;
        $first->code = 'EDIT-00001';
        $this->expectException(\InvalidArgumentException::class);
        $first->save();
    }

    public function test_rollback_and_cancelled_insert_do_not_consume_numbers(): void
    {
        $this->actingAs(User::factory()->create());
        try {
            DB::transaction(function () {
                Task::create(['title' => 'Rollback']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('MSO-00001', Task::create(['title' => 'Persistida'])->code);
        Task::creating(fn ($task) => $task->title === 'Cancelar' ? false : null);
        $cancelled = new Task(['title' => 'Cancelar']);
        $this->assertFalse($cancelled->save());
        $this->assertFalse($cancelled->exists);
        $this->assertNull($cancelled->code);
        $this->assertSame('MSO-00002', Task::create(['title' => 'Siguiente'])->code);
    }

    public function test_prefix_validation_and_global_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Siigo');
        Livewire::test(TaskSettings::class)->set('globalCodePrefix', 'sgx')->call('saveGlobalPrefix')->assertHasErrors('globalCodePrefix');
        Livewire::test(TaskSettings::class)->set('globalCodePrefix', 'abc')->call('saveGlobalPrefix')->assertHasNoErrors();
        $this->assertSame('ABC-00001', Task::create(['title' => 'Global'])->code);
        Livewire::test(TaskSettings::class)->call('openForm', $category->id)->set('codePrefix', 'A1')->call('save')->assertHasErrors('codePrefix');
        $other = User::factory()->create();
        $this->assertSame('SGX', app(ManageTaskCategories::class)->create($other, 'Siigo')->code_prefix);
    }

    public function test_code_search_and_mcp_resolution_preserve_ownership_and_uuid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Siigo');
        $task = Task::create(['title' => 'Título sin identificador', 'category' => $category->key]);
        Livewire::test(TaskList::class)->set('search', 'SGX-00001')->assertSee($task->title);
        LifeTrackerServer::actingAs($user)->tool(ListTasksTool::class, ['search' => $task->code])->assertSee($task->code);
        LifeTrackerServer::actingAs($user)->tool(GetTaskTool::class, ['task_id' => ' sgx-00001 '])->assertSee($task->code);
        LifeTrackerServer::actingAs($user)->tool(GetTaskTool::class, ['task_id' => $task->id])->assertSee($task->code);
        LifeTrackerServer::actingAs($user)->tool(UpdateTaskTool::class, ['task_id' => $task->code, 'title' => 'Actualizada'])->assertOk();
        LifeTrackerServer::actingAs($user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'code' => 'ABC-00001'])->assertHasErrors();
        LifeTrackerServer::actingAs($user)->tool(CompleteTaskTool::class, ['task_id' => $task->code, 'action' => 'complete'])->assertOk();
        $this->assertTrue($task->fresh()->completed);
        $intruder = User::factory()->create();
        LifeTrackerServer::actingAs($intruder)->tool(GetTaskTool::class, ['task_id' => $task->code])->assertHasErrors();
        $this->actingAs($intruder);
        $same = app(ManageTaskCategories::class)->create($intruder, 'Siigo');
        $own = Task::create(['title' => 'Solo intruso', 'category' => $same->key]);
        $this->assertSame($task->code, $own->code);
        $this->assertSame($own->id, app(TaskCodeService::class)->resolve($intruder->id, $task->code)->id);
    }

    public function test_explicit_owner_without_authentication_and_six_digit_numbers(): void
    {
        $user = User::factory()->create();
        auth()->logout();
        DB::table('task_code_counters')->insert(['user_id' => $user->id, 'prefix' => 'MSO', 'last_number' => 99999]);
        $this->assertSame('MSO-100000', $user->tasks()->create(['title' => 'Worker'])->code);
        $this->expectException(\InvalidArgumentException::class);
        Task::create(['title' => 'Sin propietario']);
    }

    public function test_duplicate_prefixes_are_rejected_and_category_names_do_not_change_prefixes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Siigo');
        $category->update(['name' => 'Proyecto renombrado']);
        $this->assertSame('SGX', $category->fresh()->code_prefix);
        $duplicate = app(ManageTaskCategories::class)->create($user, 'Siglas similares', codePrefix: 'SIG');
        Livewire::test(TaskSettings::class)->call('openForm', $duplicate->id)->set('codePrefix', 'sgx')->call('save')->assertHasErrors('codePrefix');
        Livewire::test(TaskSettings::class)->call('openForm', $duplicate->id)->set('codePrefix', 'MSO')->call('save')->assertHasErrors('codePrefix');
    }

    public function test_recurrence_retains_active_code_and_assigns_a_different_history_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Recurrente', 'is_recurrent' => true, 'start_date' => '2026-10-21', 'recurrence' => ['pattern' => 'daily', 'frequency' => 1]]);
        $code = $task->code;
        LifeTrackerServer::actingAs($user)->tool(CompleteTaskTool::class, ['task_id' => $code, 'action' => 'complete', 'next_occurrence_date' => '2026-10-22'])->assertOk();
        $this->assertSame($code, $task->fresh()->code);
        $this->assertSame('MSO-00002', Task::where('is_recurrence_history', true)->firstOrFail()->code);
    }

    public function test_backfill_orders_by_creation_preserves_dates_and_is_resumable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = app(ManageTaskCategories::class)->create($user, 'Siigo');
        $newer = Task::create(['title' => 'Posterior', 'category' => $category->key]);
        $older = Task::create(['title' => 'Anterior', 'category' => $category->key]);
        DB::table('tasks')->where('id', $older->id)->update(['created_at' => '2020-01-01 00:00:00']);
        DB::table('tasks')->update(['code' => null]);
        DB::table('task_categories')->update(['code_prefix' => null]);
        DB::table('task_code_counters')->delete();
        $before = DB::table('tasks')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->artisan('tasks:backfill-codes --dry-run')->assertSuccessful();
        $this->assertNull($older->fresh()->code);
        $this->artisan('tasks:backfill-codes')->assertSuccessful();
        $this->assertSame('SGX-00001', $older->fresh()->code);
        $this->assertSame('SGX-00002', $newer->fresh()->code);
        $this->artisan('tasks:backfill-codes')->assertSuccessful();
        $after = DB::table('tasks')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        foreach ($before as $i => $row) {
            unset($row['code'], $after[$i]['code']);
            $this->assertSame($row, $after[$i]);
        }
        $this->assertSame('SGX-00003', Task::create(['title' => 'Después', 'category' => $category->key])->code);
    }

    public function test_backfill_rejects_orphans_before_writing(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Huérfana']);
        DB::table('tasks')->where('id', $task->id)->update(['category' => 'missing', 'code' => null]);
        $this->artisan('tasks:backfill-codes')->assertFailed();
        $this->assertNull($task->fresh()->code);
    }
}
