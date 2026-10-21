<?php

namespace Tests\Feature;

use App\Actions\ManageTaskCategories;
use App\Livewire\Category\CategorySettings;
use App\Livewire\Meal\MealSettings;
use App\Livewire\Plan\PlanSettings;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskSettings;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Common\ManageCategoryCatalogTool;
use App\Models\DrinkType;
use App\Models\ExerciseType;
use App\Models\MealCategory;
use App\Models\PlanCategory;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserCategory;
use App\Support\CategoryCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_roots_children_and_legacy_keys_are_preserved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($user, 'Trabajo');
        $child = $manager->create($user, 'Proyecto', null, $root->id);
        $manager->update($root, 'Profesional');
        $this->assertSame('Profesional / Proyecto', $child->fresh()->path_label);
        $this->assertSame(['trabajo', 'proyecto'], TaskCategory::expandSelection([$root->key], $user->id, 'key'));
        $this->assertDatabaseHas('task_categories', ['id' => $child->id, 'parent_id' => $root->id]);
    }

    public function test_third_level_self_parent_and_foreign_owner_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $root = app(ManageTaskCategories::class)->create($user, 'Root');
        $child = app(ManageTaskCategories::class)->create($user, 'Child', null, $root->id);
        $foreign = app(ManageTaskCategories::class)->create($other, 'Foreign');
        $anotherRoot = app(ManageTaskCategories::class)->create($user, 'Another root');
        foreach ([[$root, $root->id], [$root, $child->id], [$root, $anotherRoot->id], [$child, $foreign->id]] as [$record, $parent]) {
            try {
                $record->fresh()->update(['parent_id' => $parent]);
                $this->fail('Invalid parent was accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('parent_id', $e->errors());
            }
        }
    }

    public function test_parent_deletion_does_not_reassign_its_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($user, 'Root');
        $manager->create($user, 'Child', null, $root->id);
        $task = Task::create(['title' => 'Keep', 'category' => $root->key]);
        try {
            $manager->delete($root);
            $this->fail('Parent was deleted');
        } catch (ValidationException) {
            $this->assertSame($root->key, $task->fresh()->category);
        }
    }

    public function test_task_filter_combines_parent_branch_and_uncategorized(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($user, 'Root');
        $child = $manager->create($user, 'Child', null, $root->id);
        Task::create(['title' => 'Branch task', 'category' => $child->key]);
        Task::create(['title' => 'Without category']);
        $user->taskCategories()->create(['key' => 'outside', 'name' => 'Outside']);
        Task::create(['title' => 'Excluded task', 'category' => 'outside']);
        Livewire::withQueryParams(['category' => $root->key])->test(TaskList::class)
            ->assertSet('categoryFilter', [$root->key])->assertSee('Branch task')->assertDontSee('Excluded task')
            ->set('categoryFilter', [$root->key, '__none__'])->assertSee('Without category')
            ->call('clearFilters')->assertSet('categoryFilter', []);
    }

    public function test_new_catalogs_are_scoped_by_domain_and_user_without_authentication(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $parent = UserCategory::withoutGlobalScopes()->where('user_id', $user->id)->where('domain', 'contacts')->firstOrFail();
        $child = UserCategory::create(['user_id' => $user->id, 'domain' => 'contacts', 'key' => 'child', 'name' => 'Child', 'parent_id' => $parent->id]);
        $this->assertContains('child', CategoryCatalog::expand('contacts', [$parent->key], $user->id));
        $this->assertNotContains('child', CategoryCatalog::expand('contacts', [$parent->key], $other->id));
        $this->expectException(ValidationException::class);
        $child->update(['domain' => 'mood']);
    }

    public function test_existing_settings_create_children_with_the_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $root = app(ManageTaskCategories::class)->create($user, 'Root');
        Livewire::test(TaskSettings::class)->call('openForm')
            ->set('name', 'Child')->set('parent_id', $root->id)->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('task_categories', ['name' => 'Child', 'parent_id' => $root->id, 'user_id' => $user->id]);
        $meal = MealCategory::where('user_id', $user->id)->firstOrFail();
        Livewire::test(MealSettings::class, ['section' => 'categories'])->call('openForm')
            ->set('name', 'Meal child')->set('parent_id', $meal->id)->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('meal_categories', ['name' => 'Meal child', 'parent_id' => $meal->id]);
        $plan = PlanCategory::create(['name' => 'Plan root']);
        Livewire::test(PlanSettings::class)->set('section', 'categories')->call('openForm')
            ->set('name', 'Plan child')->set('parent_id', $plan->id)->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('plan_categories', ['name' => 'Plan child', 'parent_id' => $plan->id]);
    }

    public function test_each_new_catalog_has_a_working_management_page_and_unique_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (array_keys(CategoryCatalog::DOMAINS) as $domain) {
            $this->get(route('categories.settings', $domain))->assertOk();
            $component = Livewire::test(CategorySettings::class, ['domain' => $domain])
                ->call('openForm')->set('name', 'New root')->call('save')->assertHasNoErrors();
            $root = UserCategory::where('domain', $domain)->where('name', 'New root')->firstOrFail();
            $component->call('openForm')->set('name', 'New child')->set('parent_id', $root->id)->call('save')->assertHasNoErrors()
                ->call('delete', $root->id)->assertSee('Mueve primero');
            $component->call('openForm')->set('name', 'new ROOT')->call('save')->assertHasErrors('name');
        }
    }

    public function test_migration_preserves_legacy_assignments_and_rollback_custom_labels(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $drink = DrinkType::create(['name' => 'Legacy beverage', 'hydration_factor' => 1, 'category' => 'legacy-drink']);
        $category = UserCategory::where('domain', 'drinks')->where('key', 'legacy-drink')->firstOrFail();
        $category->update(['name' => 'Renamed beverage group']);
        $migration = require database_path('migrations/2026_10_13_000001_add_category_hierarchies.php');
        $migration->down();
        $this->assertSame('Renamed beverage group', $drink->fresh()->category);
        $migration->up();
        $this->assertDatabaseHas('user_categories', ['user_id' => $user->id, 'domain' => 'drinks', 'key' => 'Renamed beverage group', 'name' => 'Renamed beverage group', 'parent_id' => null]);
    }

    public function test_mcp_catalogs_list_parents_and_reject_deleting_a_branch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $root = UserCategory::where('domain', 'contacts')->firstOrFail();
        UserCategory::create(['user_id' => $user->id, 'domain' => 'contacts', 'name' => 'Client', 'key' => 'client', 'parent_id' => $root->id]);
        LifeTrackerServer::actingAs($user)->tool(ManageCategoryCatalogTool::class,
            ['domain' => 'contacts', 'action' => 'list'])->assertOk()->assertSee($root->id);
        LifeTrackerServer::actingAs($user)->tool(ManageCategoryCatalogTool::class,
            ['domain' => 'contacts', 'action' => 'delete', 'id' => $root->id])->assertHasErrors();
    }

    public function test_database_rejects_foreign_owner_and_domain_parents(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = UserCategory::withoutGlobalScopes()->where('user_id', $user->id)->where('domain', 'contacts')->firstOrFail();
        foreach ([[$other->id, 'contacts'], [$user->id, 'mood']] as [$owner, $domain]) {
            $foreign = UserCategory::withoutGlobalScopes()->where('user_id', $owner)->where('domain', $domain)->firstOrFail();
            try {
                DB::table('user_categories')->where('id', $category->id)->update(['parent_id' => $foreign->id]);
                $this->fail('Foreign parent was accepted by the database');
            } catch (QueryException) {
                $this->assertNull($category->fresh()->parent_id);
            }
        }
    }

    public function test_imports_keep_stable_keys_and_catalog_deletion_preserves_used_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $type = ExerciseType::create(['name' => 'Imported exercise', 'category' => 'legacy-custom', 'calories_per_hour' => 100, 'steps_equivalent' => 0]);
        $category = UserCategory::where('domain', 'exercise')->where('key', 'legacy-custom')->firstOrFail();
        $category->update(['name' => 'Personal training']);
        $this->assertSame('legacy-custom', $type->fresh()->category);
        $this->assertSame('Personal training', $type->fresh()->category_label);
        $this->expectException(ValidationException::class);
        $category->delete();
    }

    public function test_explicit_owner_operations_still_see_children_with_another_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $manager = app(ManageTaskCategories::class);
        $root = $manager->create($owner, 'Owner root');
        $child = $manager->create($owner, 'Owner child', null, $root->id);
        $this->assertSame('Owner root / Owner child', $child->fresh()->path_label);
        $this->expectException(ValidationException::class);
        $manager->delete($root);
    }
}
