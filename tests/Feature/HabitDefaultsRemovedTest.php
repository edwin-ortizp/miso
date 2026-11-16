<?php

namespace Tests\Feature;

use App\Livewire\Habit\HabitSettings;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitDefaultsRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_habit_settings_offer_no_personal_routine(): void
    {
        $this->assertFalse(method_exists(HabitSettings::class, 'restoreDefaults'));

        $this->actingAs(User::factory()->create());
        Livewire::test(HabitSettings::class)
            ->assertSee('Aún no tienes hábitos')
            ->assertSee('Crear mi primer hábito')
            ->assertDontSee('Restaurar hábitos predeterminados')
            ->assertDontSee('Agregar predeterminados');
    }

    public function test_base_seeder_never_creates_the_authors_account_or_personal_habits(): void
    {
        $this->app['env'] = 'production';
        (new DatabaseSeeder)->run();
        $this->assertSame(0, User::where('email', 'edwin.ortizp123@gmail.com')->count());
        $this->assertSame(0, User::count());
    }

    public function test_local_seeder_creates_only_a_fictitious_demo_account(): void
    {
        $this->app['env'] = 'local';
        (new DatabaseSeeder)->run();
        $this->assertSame(['demo@example.test'], User::pluck('email')->all());
        $this->assertSame(0, User::where('email', 'like', '%gmail.com')->count());
    }
}
