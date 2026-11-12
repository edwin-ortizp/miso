<?php

namespace Tests\Feature;

use App\Models\GoalEntry;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\RecipeIngredient;
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

    public function test_goal_tasks_table_is_not_part_of_the_new_schema(): void
    {
        $this->assertFalse(Schema::hasTable('goal_tasks'));
    }
}
