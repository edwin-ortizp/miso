<?php

namespace Tests\Feature;

use App\Livewire\Goal\GoalIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-119: el inicio que supera la fecha límite la alinea; en otro caso no la toca. */
class GoalStartDateAlignTest extends TestCase
{
    use RefreshDatabase;

    private function index()
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(GoalIndex::class);
    }

    public function test_start_after_due_aligns_the_due_date(): void
    {
        $this->index()->set('dueDate', '2026-10-10')->set('startDate', '2026-10-12')->assertSet('dueDate', '2026-10-12');
    }

    public function test_start_before_due_keeps_the_due_date(): void
    {
        $this->index()->set('dueDate', '2026-12-31')->set('startDate', '2026-10-12')->assertSet('dueDate', '2026-12-31');
    }

    public function test_without_due_date_nothing_is_invented(): void
    {
        $this->index()->set('startDate', '2026-10-12')->assertSet('dueDate', null);
    }
}
