<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalogs\ModuleCatalogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleCatalogsTest extends TestCase
{
    use RefreshDatabase;

    private function newUser(): User
    {
        return User::create(['name' => 'Persona', 'email' => fake()->unique()->safeEmail(), 'password' => 'password']);
    }

    public function test_opening_a_module_seeds_its_catalog_once(): void
    {
        $user = $this->newUser();
        $this->actingAs($user);
        $this->assertSame(0, DB::table('exercise_types')->where('user_id', $user->id)->count());

        $this->get(route('exercise'))->assertOk();
        $count = DB::table('exercise_types')->where('user_id', $user->id)->count();
        $this->assertGreaterThan(0, $count);
        $this->assertSame(0, DB::table('drink_types')->where('user_id', $user->id)->count());

        DB::table('exercise_types')->where('user_id', $user->id)->limit(1)->delete();
        ModuleCatalogs::forget($user->id);
        $this->get(route('exercise'))->assertOk();
        $this->assertSame($count - 1, DB::table('exercise_types')->where('user_id', $user->id)->count());
    }

    public function test_each_module_seeds_only_its_own_domains(): void
    {
        $user = $this->newUser();
        ModuleCatalogs::ensure($user->id, 'water');

        $this->assertGreaterThan(0, DB::table('drink_types')->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, DB::table('user_categories')->where('user_id', $user->id)->where('domain', 'drinks')->count());
        $this->assertSame(0, DB::table('user_categories')->where('user_id', $user->id)->where('domain', 'exercise')->count());
        $this->assertSame(0, DB::table('mood_states')->where('user_id', $user->id)->count());
    }

    public function test_ensure_all_seeds_every_module_for_mcp_and_api(): void
    {
        $user = $this->newUser();
        ModuleCatalogs::ensureAll($user->id);

        foreach (['exercise_types', 'drink_types', 'mood_states', 'circles', 'vehicle_expense_categories', 'meal_categories', 'task_categories'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->where('user_id', $user->id)->count(), $table);
        }
        $this->assertEqualsCanonicalizing(ModuleCatalogs::MODULES, ModuleCatalogs::provisioned($user->id));
    }

    public function test_migration_marks_existing_accounts_so_deleted_defaults_stay_deleted(): void
    {
        $user = $this->newUser();
        DB::table('module_settings')->where('module', 'catalogs')->delete();
        (require database_path('migrations/2026_11_16_000001_mark_existing_catalogs_provisioned.php'))->up();
        ModuleCatalogs::forget($user->id);

        $this->actingAs($user)->get(route('exercise'))->assertOk();
        $this->assertSame(0, DB::table('exercise_types')->where('user_id', $user->id)->count());
    }
}
