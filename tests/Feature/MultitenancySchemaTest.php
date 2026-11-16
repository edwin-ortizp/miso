<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\GoalEntry;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\ModuleSetting;
use App\Models\RecipeIngredient;
use App\Models\Relationship;
use App\Models\User;
use App\Services\Experience\PersonalExperience;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultitenancySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_starts_private_guides_without_copying_habit_catalogs(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('onboarding'));

        $this->post('/logout');

        $this->post('/register', [
            'name' => 'Bruno',
            'email' => 'bruno@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('onboarding'));

        $ana = User::where('email', 'ana@example.test')->firstOrFail();
        $bruno = User::where('email', 'bruno@example.test')->firstOrFail();

        $this->assertSame(0, HabitDefinition::withoutGlobalScopes()->where('user_id', $ana->id)->count());
        $this->assertSame(0, $ana->negativeHabitDefinitions()->withoutGlobalScopes()->count());
        $this->assertSame(0, HabitDefinition::withoutGlobalScopes()->where('user_id', $bruno->id)->count());
        $this->assertSame(0, $bruno->negativeHabitDefinitions()->withoutGlobalScopes()->count());
        $this->assertSame(0, HabitDefinition::withoutGlobalScopes()->count());
        $experience = app(PersonalExperience::class);
        $this->assertTrue($experience->required($ana->id));
        $this->assertTrue($experience->required($bruno->id));
        $this->assertDatabaseCount('module_settings', 2);
    }

    public function test_habits_are_scoped_and_cannot_reference_another_users_definition(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerHabit = $owner->habitDefinitions()->create(['name' => 'Leer', 'time_of_day' => 'night']);
        $otherHabit = $other->habitDefinitions()->create(['name' => 'Meditar', 'time_of_day' => 'morning']);

        $this->actingAs($owner);

        $this->assertSame([$ownerHabit->id], HabitDefinition::pluck('id')->all());

        $this->expectException(QueryException::class);
        HabitCompletion::create([
            'habit_id' => $otherHabit->id,
            'date' => '2026-07-12',
            'completed' => true,
        ]);
    }

    public function test_goal_entries_and_recipe_ingredients_cannot_cross_tenant_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $otherGoal = $other->goals()->create(['title' => 'Objetivo ajeno']);
        $otherRecipe = $other->recipes()->create(['name' => 'Receta ajena']);
        $ownerItem = $owner->shoppingItems()->create(['name' => 'Avena', 'status' => 'pending']);

        $this->actingAs($owner);

        try {
            GoalEntry::create([
                'goal_id' => $otherGoal->id,
                'text' => 'Intento inválido',
                'date' => '2026-07-12',
            ]);
            $this->fail('Una entrada no puede apuntar a un objetivo de otro usuario.');
        } catch (QueryException) {
            $this->assertDatabaseCount('goal_entries', 0);
        }

        try {
            RecipeIngredient::create([
                'recipe_id' => $otherRecipe->id,
                'shopping_item_id' => $ownerItem->id,
                'quantity' => 1,
            ]);
            $this->fail('Un ingrediente no puede enlazar recursos de usuarios distintos.');
        } catch (QueryException) {
            $this->assertDatabaseCount('recipe_ingredients', 0);
        }
    }

    public function test_registration_gives_each_account_a_unique_friend_code_and_no_username(): void
    {
        foreach (['ana', 'bruno'] as $name) {
            $this->post('/register', [
                'name' => ucfirst($name), 'email' => "{$name}@example.test",
                'password' => 'password', 'password_confirmation' => 'password',
            ])->assertRedirect(route('onboarding'));
            $this->post('/logout');
        }

        $codes = User::pluck('friend_code')->all();
        $this->assertCount(2, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^['.User::FRIEND_CODE_ALPHABET.']{8}$/', (string) $code);
        }
        $this->assertSame(0, User::whereNotNull('username')->count());
        $this->assertSame(0, ModuleSetting::withoutGlobalScopes()->where('module', 'social')->count(), 'lo social se crea bajo demanda');
    }

    public function test_friendship_pair_and_username_are_unique(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => Friendship::PENDING]);

        try {
            Friendship::create(['requester_id' => $b->id, 'addressee_id' => $a->id, 'status' => Friendship::PENDING]);
            $this->fail('Una solicitud cruzada no puede duplicar el par.');
        } catch (QueryException) {
            $this->assertDatabaseCount('friendships', 1);
        }

        $a->update(['username' => 'repetido']);
        $this->expectException(QueryException::class);
        $b->update(['username' => 'repetido']);
    }

    public function test_deleting_an_account_cascades_its_social_data_and_keeps_other_accounts_intact(): void
    {
        [$a, $b, $c] = User::factory()->count(3)->create();
        Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => Friendship::ACCEPTED]);
        Friendship::create(['requester_id' => $b->id, 'addressee_id' => $c->id, 'status' => Friendship::BLOCKED, 'blocked_by' => $c->id]);
        ModuleSetting::withoutGlobalScopes()->create(['user_id' => $a->id, 'module' => 'social', 'settings' => ['social_visible' => true]]);
        $ficha = Relationship::factory()->create(['user_id' => $b->id, 'friend_user_id' => $a->id]);

        $a->delete();

        $this->assertDatabaseCount('friendships', 1);
        $this->assertSame(0, Friendship::where('requester_id', $a->id)->orWhere('addressee_id', $a->id)->count());
        $this->assertSame(0, ModuleSetting::withoutGlobalScopes()->where('user_id', $a->id)->count());
        $this->assertNull(Relationship::withoutGlobalScopes()->findOrFail($ficha->id)->friend_user_id, 'la ficha ajena se conserva sin vínculo');

        // Quien bloqueó se va: no quedan filas huérfanas (blocked_by y el par).
        $c->delete();
        $this->assertDatabaseCount('friendships', 0);
        $this->assertNotNull(User::find($b->id));
    }

    public function test_social_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['username', 'friend_code']));
        $this->assertTrue(Schema::hasTable('friendships'));
        $this->assertTrue(Schema::hasColumn('relationships', 'friend_user_id'));
    }

    public function test_goal_tasks_table_is_not_part_of_the_new_schema(): void
    {
        $this->assertFalse(Schema::hasTable('goal_tasks'));
    }
}
