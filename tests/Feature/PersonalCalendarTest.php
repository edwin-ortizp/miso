<?php

namespace Tests\Feature;

use App\Livewire\Learning\StudyReservationEditor;
use App\Livewire\Task\TaskCalendar;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\HealthEvent;
use App\Models\MenstrualCycle;
use App\Models\StudyReservation;
use App\Models\Task;
use App\Models\User;
use App\Services\Learning\AcademicRecords;
use App\Services\Learning\LearningRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_habits_and_opt_in_health_are_limited_to_the_visible_range(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17));
        $this->actingAs(User::factory()->create());
        $habit = HabitDefinition::create(['name' => 'Leer', 'show_in_calendar' => true, 'base_time' => '07:00']);
        HabitDefinition::create(['name' => 'Oculto']);
        HabitCompletion::create(['habit_id' => $habit->id, 'date' => '2026-11-16', 'completed' => true]);
        HealthEvent::create(['type' => 'appointment', 'title' => 'Detalle privado', 'event_date' => '2026-11-17', 'is_sensitive' => true]);
        MenstrualCycle::create(['started_on' => '2026-11-16', 'notes' => 'privado']);

        $calendar = new TaskCalendar;
        DB::enableQueryLog();
        try {
            $habits = $calendar->events('2026-11-15', '2026-11-19', ['habits']);
            $this->assertCount(3, $habits);
            $this->assertSame('✓ Leer · Completado', $habits[0]['title']);
            $this->assertSame('2026-11-18T07:00:00', $habits[2]['start']);
            $this->assertCount(2, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $health = $calendar->events('2026-11-16', '2026-11-19', ['health']);
        $this->assertSame(['Cita médica', 'Menstruación'], array_column($health, 'title'));
        $this->assertSame('2026-11-18', $health[1]['end']);

        $this->actingAs(User::factory()->create());
        $this->assertSame([], $calendar->events('2026-11-15', '2026-11-19', ['habits', 'health']));
    }

    public function test_reservation_exceptions_move_and_cancel_occurrences_without_modifying_the_series(): void
    {
        $this->actingAs(User::factory()->create());
        $editor = Livewire::test(StudyReservationEditor::class)
            ->call('create')
            ->set('draft.date', '2026-11-16')
            ->set('draft.frequency', 'weekly')
            ->set('draft.weekdays', [1, 3])
            ->call('save')
            ->assertHasNoErrors();
        $series = StudyReservation::sole();
        $editor->call('edit', $series->id, '2026-11-25')
            ->set('draft.title', 'Trabajo individual')
            ->call('save')->assertHasNoErrors();
        $calendar = new TaskCalendar;
        $calendar->moveReservation($series->id, '2026-11-16', '2026-11-24T13:00:00', '2026-11-24T14:00:00');
        $editor->call('edit', $series->id, '2026-11-18')->call('cancelReservation');

        $this->assertSame([], $calendar->events('2026-11-16', '2026-11-23', ['reservations']));
        $events = $calendar->events('2026-11-24', '2026-11-26', ['reservations']);
        $this->assertCount(2, $events);
        $this->assertContains('2026-11-24T13:00:00', array_column($events, 'start'));
        $this->assertContains('Trabajo individual', array_column($events, 'title'));
        $this->assertSame('2026-11-16', $series->fresh()->date);
        $owner = auth()->user();
        $this->actingAs(User::factory()->create());
        $this->assertSame([], $calendar->events('2026-11-24', '2026-11-26', ['reservations']));

        $this->actingAs($owner);
        $editor->call('edit', $series->id, '2026-11-25')
            ->call('changeScope', 'series')->set('draft.title', 'Nueva serie')->call('save')->assertHasNoErrors();
        $this->assertSame(0, $series->exceptions()->count());
        $this->assertSame(['Nueva serie'], array_column($calendar->events('2026-11-24', '2026-11-26', ['reservations']), 'title'));
    }

    public function test_reservation_search_is_bounded_and_keeps_owned_links_that_are_completed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner);

        $learning = app(LearningRecords::class);
        $formation = $learning->saveFormation($owner->id, null, [
            'title' => 'Formación de la reserva', 'type' => 'course', 'status' => 'pending',
        ]);
        $otherFormation = $learning->saveFormation($owner->id, null, [
            'title' => 'Otra formación', 'type' => 'degree', 'status' => 'pending',
        ]);
        $subject = $learning->saveUnit($owner->id, $otherFormation->id, null, null, 'subject', [
            'title' => 'Matemáticas', 'status' => 'pending',
        ]);
        $first = Task::create(['title' => 'Álgebra']);
        $second = app(AcademicRecords::class)->saveTask($owner->id, $subject->id, (string) Str::uuid(), [
            'title' => 'Geometría',
        ], false);
        $completed = Task::create(['title' => 'Cálculo completado', 'completed' => true]);
        $this->actingAs($other);
        $foreign = Task::create(['title' => 'Cálculo privado']);
        $this->actingAs($owner);
        for ($i = 0; $i < 22; $i++) {
            Task::create(['title' => 'Cálculo '.sprintf('%02d', $i)]);
        }

        $editor = Livewire::test(StudyReservationEditor::class)
            ->call('create')
            ->assertViewHas('taskOptions', fn ($options) => $options->isEmpty())
            ->set('draft.formation_id', $formation->id)
            ->set('taskSearch', 'Cálculo')
            ->assertViewHas('taskOptions', fn ($options) => $options->count() === 20)
            ->assertViewHas('moreTasks', true)
            ->set('taskSearch', 'Cálculo privado')
            ->assertViewHas('taskOptions', fn ($options) => $options->isEmpty())
            ->set('taskSearch', 'Geometría')
            ->assertViewHas('taskOptions', fn ($options) => $options->all() === [$second->id => $second->title])
            ->set('draft.task_ids', [$first->id, $second->id, $completed->id, $foreign->id])
            ->call('save')
            ->assertHasErrors(['draft.task_ids.2', 'draft.task_ids.3'])
            ->assertSet('validationSection', 'tasks')
            ->set('draft.task_ids', [$first->id, $second->id])
            ->call('save')->assertHasNoErrors();

        $reservation = StudyReservation::sole();
        $this->assertSame($formation->id, $reservation->formation_id);
        $first->update(['completed' => true]);
        $editor->call('edit', $reservation->id, $reservation->date)
            ->assertViewHas('selectedTasks', fn ($options) => $options->has($first->id))
            ->set('taskSearch', 'Álgebra')
            ->assertViewHas('taskOptions', fn ($options) => $options->isEmpty())
            ->call('save')->assertHasNoErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $reservation->fresh()->task_ids);

        $editor->call('edit', $reservation->id, $reservation->date)
            ->set('draft.task_ids', [$second->id])
            ->call('save')->assertHasNoErrors();
        $this->assertSame([$second->id], $reservation->fresh()->task_ids);
    }

    public function test_switching_scope_checks_for_unsaved_changes_before_loading_the_series(): void
    {
        $this->actingAs(User::factory()->create());
        $editor = Livewire::test(StudyReservationEditor::class)
            ->call('create')
            ->set('draft.frequency', 'daily')
            ->call('save')->assertHasNoErrors();
        $series = StudyReservation::sole();

        $editor->call('edit', $series->id, today()->addDay()->toDateString())
            ->set('draft.title', 'Borrador de ocurrencia')
            ->call('changeScope', 'series')
            ->assertReturned(false)
            ->assertSet('scope', 'one')
            ->assertSet('draft.title', 'Borrador de ocurrencia')
            ->call('changeScope', 'series', true)
            ->assertReturned(true)
            ->assertSet('scope', 'series')
            ->assertSet('draft.title', $series->title);
    }
}
