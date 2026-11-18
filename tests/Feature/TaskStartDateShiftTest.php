<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskEditor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskStartDateShiftTest extends TestCase
{
    use RefreshDatabase;

    private function editor()
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(TaskEditor::class);
    }

    public function test_same_day_task_moves_both_dates(): void
    {
        $this->editor()->set('startDate', '2026-10-10')->set('endDate', '2026-10-10')
            ->set('startDate', '2026-10-12')
            ->assertSet('startDate', '2026-10-12')->assertSet('endDate', '2026-10-12');
    }

    public function test_multi_day_task_keeps_its_duration(): void
    {
        $this->editor()->set('startDate', '2026-10-10')->set('endDate', '2026-10-13')
            ->set('startDate', '2026-10-12')->assertSet('endDate', '2026-10-15')
            ->set('startDate', '2026-10-09')->assertSet('endDate', '2026-10-12');
    }

    public function test_changing_only_the_end_does_not_move_the_start(): void
    {
        $this->editor()->set('startDate', '2026-10-10')->set('endDate', '2026-10-10')
            ->set('endDate', '2026-10-14')->assertSet('startDate', '2026-10-10')->assertSet('endDate', '2026-10-14');
    }

    public function test_without_end_nothing_is_invented_and_an_early_end_is_aligned(): void
    {
        $this->editor()->set('startDate', '2026-10-12')->assertSet('endDate', null);
        $this->editor()->set('endDate', '2026-10-10')->set('startDate', '2026-10-12')->assertSet('endDate', '2026-10-12');
    }

    public function test_clearing_the_start_keeps_the_end(): void
    {
        $this->editor()->set('startDate', '2026-10-10')->set('endDate', '2026-10-11')
            ->set('startDate', null)->assertSet('endDate', '2026-10-11');
    }

    public function test_manual_duration_and_end_changes_are_saved_across_midnight(): void
    {
        $this->editor()->call('openForm')
            ->set('title', 'Bloque nocturno')
            ->set('estimatedTime', 45)
            ->set('startDate', '2026-11-18')->assertSet('endDate', null)
            ->set('startTime', '23:30')
            ->assertSet('endDate', '2026-11-19')->assertSet('endTime', '00:15')
            ->set('estimatedTime', 90)->assertSet('endTime', '01:00')
            ->set('endTime', '01:15')->assertSet('estimatedTime', 105)
            ->set('endDate', '2026-11-20')->assertSet('estimatedTime', 1545)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Bloque nocturno',
            'start_date' => '2026-11-18 23:30:00',
            'end_date' => '2026-11-20 01:15:00',
            'estimated_time' => 1545,
        ]);
    }

    public function test_moving_an_existing_start_keeps_the_saved_duration(): void
    {
        $editor = $this->editor();
        $task = Task::create([
            'user_id' => auth()->id(), 'title' => 'Bloque existente',
            'start_date' => '2026-11-18 21:00:00', 'start_is_date' => false,
            'end_date' => '2026-11-18 22:00:00', 'end_is_date' => false,
            'estimated_time' => 90,
        ]);

        $editor->call('openScheduleForm', $task->id)
            ->set('startTime', '23:00')
            ->assertSet('endDate', '2026-11-19')->assertSet('endTime', '00:30')
            ->set('startDate', '2026-11-21')
            ->assertSet('endDate', '2026-11-22')->assertSet('endTime', '00:30')
            ->assertSet('estimatedTime', 90)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id, 'start_date' => '2026-11-21 23:00:00',
            'end_date' => '2026-11-22 00:30:00', 'estimated_time' => 90,
        ]);
    }

    public function test_a_timed_range_without_a_saved_estimate_keeps_its_duration_when_moved(): void
    {
        $editor = $this->editor();
        $task = Task::create([
            'user_id' => auth()->id(), 'title' => 'Sin estimación',
            'start_date' => '2026-11-18 23:00:00', 'start_is_date' => false,
            'end_date' => '2026-11-19 01:00:00', 'end_is_date' => false,
        ]);

        $editor->call('openForm', $task->id)->set('startTime', '20:00')
            ->assertSet('endDate', '2026-11-18')->assertSet('endTime', '22:00')
            ->assertSet('estimatedTime', 120);
    }

    public function test_an_end_before_the_start_cannot_be_saved_and_can_be_corrected(): void
    {
        $editor = $this->editor()->call('openForm')
            ->set('title', 'Intervalo corregido')
            ->set('startDate', '2026-11-18')->set('startTime', '23:30')
            ->set('endDate', '2026-11-18')->set('endTime', '00:05')
            ->call('save')->assertHasErrors('endDate');

        $this->assertDatabaseMissing('tasks', ['title' => 'Intervalo corregido']);

        $editor->set('endDate', '2026-11-19')->assertSet('estimatedTime', 35)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Intervalo corregido', 'estimated_time' => 35]);
    }
}
