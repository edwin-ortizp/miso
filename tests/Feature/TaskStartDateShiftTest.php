<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskEditor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-118: mover el inicio desplaza el fin los mismos días. */
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
}
