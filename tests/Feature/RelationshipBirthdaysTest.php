<?php

namespace Tests\Feature;

use App\Livewire\Relationship\RelationshipBirthdays;
use App\Livewire\Relationship\RelationshipIndex;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RelationshipBirthdaysTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-20 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_birthday_without_a_year_is_kept_without_showing_an_age(): void
    {
        Relationship::factory()->withBirthday(11, 3)->create([
            'user_id' => $this->user->id,
            'full_name' => 'Camilo Vargas',
        ]);

        Livewire::test(RelationshipBirthdays::class)
            ->assertSee('Camilo Vargas')
            ->assertSee('3 de noviembre')
            ->assertDontSee('md-chip-tonal--info', false);
    }

    public function test_an_impossible_birthday_is_rejected_by_the_form(): void
    {
        Livewire::test(RelationshipIndex::class)
            ->call('openForm')
            ->set('fullName', 'Fecha imposible')
            ->set('birthdayMonth', 4)
            ->set('birthdayDay', 31)
            ->call('save')
            ->assertHasErrors('birthdayDay');
    }

    public function test_a_january_birthday_is_listed_next_when_consulted_in_december(): void
    {
        Relationship::factory()->withBirthday(1, 8)->create([
            'user_id' => $this->user->id,
            'full_name' => 'Enero Pronto',
        ]);
        Relationship::factory()->withBirthday(11, 3)->create([
            'user_id' => $this->user->id,
            'full_name' => 'Noviembre Lejos',
        ]);

        Livewire::test(RelationshipBirthdays::class)
            ->assertSeeInOrder(['Enero Pronto', 'Noviembre Lejos']);
    }

    public function test_a_birthday_today_is_labelled_as_today(): void
    {
        Relationship::factory()->withBirthday(12, 20)->create([
            'user_id' => $this->user->id,
            'full_name' => 'Cumple Hoy',
        ]);

        Livewire::test(RelationshipBirthdays::class)
            ->assertSee('Cumple Hoy')
            ->assertSee('Hoy');
    }

    public function test_the_month_selector_shows_only_that_month_ordered_by_day(): void
    {
        Relationship::factory()->withBirthday(11, 20)->create(['user_id' => $this->user->id, 'full_name' => 'Noviembre Tarde']);
        Relationship::factory()->withBirthday(11, 3)->create(['user_id' => $this->user->id, 'full_name' => 'Noviembre Temprano']);
        Relationship::factory()->withBirthday(5, 17)->create(['user_id' => $this->user->id, 'full_name' => 'Mayo Aparte']);

        Livewire::test(RelationshipBirthdays::class)
            ->set('monthFilter', '11')
            ->assertSeeInOrder(['Noviembre Temprano', 'Noviembre Tarde'])
            ->assertDontSee('Mayo Aparte');

        $this->get('/relationships/birthdays?month=11')->assertOk()->assertDontSee('Mayo Aparte');
    }

    public function test_the_age_is_shown_only_when_the_birth_year_is_known(): void
    {
        Relationship::factory()->withBirthday(1, 8, 1990)->create(['user_id' => $this->user->id, 'full_name' => 'Con Año']);
        Relationship::factory()->withBirthday(1, 9)->create(['user_id' => $this->user->id, 'full_name' => 'Sin Año']);

        Livewire::test(RelationshipBirthdays::class)
            ->assertSee('Cumple 37')
            ->assertSee('Sin Año');
    }

    public function test_february_29_is_ordered_on_february_28_in_a_common_year(): void
    {
        Relationship::factory()->withBirthday(2, 29, 2000)->create(['user_id' => $this->user->id, 'full_name' => 'Bisiesto']);
        Relationship::factory()->withBirthday(3, 1)->create(['user_id' => $this->user->id, 'full_name' => 'Primero de Marzo']);

        Livewire::test(RelationshipBirthdays::class)
            ->assertSeeInOrder(['Bisiesto', 'Primero de Marzo']);

        $stored = Relationship::firstWhere('full_name', 'Bisiesto');
        $this->assertSame(2, $stored->birthday_month);
        $this->assertSame(29, $stored->birthday_day, 'The stored birthday keeps 29 February.');
    }

    public function test_crossing_into_a_new_year_recalculates_without_creating_events(): void
    {
        Relationship::factory()->withBirthday(1, 8)->create(['user_id' => $this->user->id, 'full_name' => 'Enero Pronto']);

        Livewire::test(RelationshipBirthdays::class)->assertSee('Enero Pronto');
        $this->assertDatabaseCount('relationship_events', 0);

        Carbon::setTestNow('2027-01-09 09:00:00');

        Livewire::test(RelationshipBirthdays::class)->assertSee('Enero Pronto');
        $this->assertDatabaseCount('relationship_events', 0);
    }

    public function test_archived_relationships_are_left_out_of_the_birthday_view(): void
    {
        Relationship::factory()->withBirthday(1, 8)->archived()
            ->create(['user_id' => $this->user->id, 'full_name' => 'Archivada Persona']);

        Livewire::test(RelationshipBirthdays::class)->assertDontSee('Archivada Persona');
    }

    public function test_a_task_can_be_started_from_a_birthday_with_the_chosen_date(): void
    {
        $relationship = Relationship::factory()->withBirthday(1, 8)->create([
            'user_id' => $this->user->id,
            'full_name' => 'Enero Pronto',
        ]);

        Livewire::test(\App\Livewire\Task\TaskEditor::class)
            ->call('openBirthdayForm', $relationship->id)
            ->assertSet('endDate', '2027-01-08')
            ->set('endDate', '2027-01-02')
            ->set('title', 'Comprar regalo')
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::firstWhere('title', 'Comprar regalo');

        $this->assertSame('2027-01-02', $task->end_date->toDateString());
        $this->assertTrue($relationship->tasks()->whereKey($task->id)->exists());
        $this->assertDatabaseCount('relationship_events', 0);
    }
}
