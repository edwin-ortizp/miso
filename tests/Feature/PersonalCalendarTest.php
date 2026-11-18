<?php

namespace Tests\Feature;

use App\Livewire\Learning\StudyReservationEditor;
use App\Livewire\Task\TaskCalendar;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\HealthEvent;
use App\Models\MenstrualCycle;
use App\Models\StudyReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->set('scope', 'series')->set('draft.title', 'Nueva serie')->call('save')->assertHasNoErrors();
        $this->assertSame(0, $series->exceptions()->count());
        $this->assertSame(['Nueva serie'], array_column($calendar->events('2026-11-24', '2026-11-26', ['reservations']), 'title'));
    }
}
