<?php

namespace Tests\Feature;

use App\Livewire\Relationship\RelationshipBirthdays;
use App\Livewire\Relationship\RelationshipEvents;
use App\Livewire\Relationship\RelationshipIndex;
use App\Livewire\Relationship\RelationshipShow;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use App\Support\EventDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end walkthrough of the four reference flows: a monthly graduation, a sensitive
 * health entry, an appointment reminder and the birthday view.
 */
class RelationshipAlisonWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-04 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_whole_alison_journey_works_from_profile_to_birthday(): void
    {
        // 1. Her profile is created with a birthday and a phone number.
        Livewire::test(RelationshipIndex::class)
            ->call('openForm')
            ->set('fullName', 'Alison Restrepo')
            ->set('nickname', 'Ali')
            ->set('birthdayMonth', 8)
            ->set('birthdayDay', 30)
            ->set('birthdayYear', 1996)
            ->set('contactFrequencyDays', 20)
            ->set('contactMethods', [
                ['id' => null, 'type' => 'phone', 'label' => 'Personal', 'value' => '300 555 1010', 'is_primary' => true],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $alison = Relationship::firstWhere('full_name', 'Alison Restrepo');
        $detail = fn () => Livewire::test(RelationshipShow::class, ['relationship' => $alison->id]);

        // 2. A graduation known only by month keeps that precision everywhere.
        $detail()
            ->call('openEventForm')
            ->set('eventTitle', 'Graduación de Alison')
            ->set('eventCategory', 'education-work')
            ->set('eventPrecision', EventDate::MONTH)
            ->set('eventYear', 2026)
            ->set('eventMonth', 11)
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertSee('noviembre de 2026');

        Livewire::test(RelationshipEvents::class)
            ->assertSee('Graduación de Alison')
            ->assertSee('noviembre de 2026')
            ->assertDontSee('1 de noviembre de 2026');

        // 3. A sensitive health entry lives in her timeline but not in the global view.
        $detail()
            ->call('openEventForm')
            ->set('eventTitle', 'Resultado de exámenes')
            ->set('eventCategory', 'health')
            ->set('eventPrecision', EventDate::DAY)
            ->set('eventDate', '2026-08-12')
            ->set('eventIsSensitive', true)
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertSee('Resultado de exámenes');

        Livewire::test(RelationshipEvents::class)
            ->assertDontSee('Resultado de exámenes')
            ->set('includeSensitive', true)
            ->assertSee('Resultado de exámenes');

        // 4. An appointment reminder is one ordinary task, not a second event.
        Livewire::test(\App\Livewire\Task\TaskEditor::class)
            ->call('openContactForm', $alison->id)
            ->set('title', 'Recordarle pedir cita médica')
            ->set('endDate', '2026-08-10')
            ->call('save')
            ->assertHasNoErrors();

        $detail()->assertSee('Recordarle pedir cita médica');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('relationship_events', 2);

        // 5. Her birthday is listed with the age she turns, without persisting an event.
        Livewire::test(RelationshipBirthdays::class)
            ->assertSee('Alison Restrepo')
            ->assertSee('30 de agosto de 1996')
            ->assertSee('Cumple 30')
            ->assertSee('en 26 días');

        $this->assertDatabaseCount('relationship_events', 2);

        // 6. Deleting her keeps the task and drops only the association.
        $task = Task::firstWhere('title', 'Recordarle pedir cita médica');
        Livewire::test(RelationshipIndex::class)->call('delete', $alison->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseCount('task_associations', 0);
        $this->assertDatabaseCount('relationship_events', 0);
    }
}
