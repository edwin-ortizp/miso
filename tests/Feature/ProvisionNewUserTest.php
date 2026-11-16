<?php

namespace Tests\Feature;

use App\Actions\ProvisionNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProvisionNewUserTest extends TestCase
{
    use RefreshDatabase;

    private function newUser(): User
    {
        return User::create(['name' => 'Persona', 'email' => fake()->unique()->safeEmail(), 'password' => 'password']);
    }

    private const OTHER_TABLES = ['exercise_types', 'drink_types', 'mood_states', 'circles', 'vehicle_expense_categories', 'meal_categories', 'user_categories'];

    public function test_registration_only_creates_task_catalog(): void
    {
        $this->post('/register', ['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('onboarding'));

        $user = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertSame(11, DB::table('task_categories')->where('user_id', $user->id)->count());
        foreach (self::OTHER_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $user->id)->count(), $table);
        }
    }

    public function test_provisioning_is_idempotent_and_does_not_restore_deleted_values(): void
    {
        $user = $this->newUser();
        ProvisionNewUser::for($user);
        DB::table('task_categories')->where('user_id', $user->id)->where('key', 'otros')->delete();
        ProvisionNewUser::for($user);

        $this->assertSame(10, DB::table('task_categories')->where('user_id', $user->id)->count());
    }
}
