<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskList;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskListSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_order_puts_recently_edited_tasks_first_before_pagination(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->startOfSecond());
        $old = Task::create(['title' => 'Antigua', 'start_date' => today()->addMonth()]);
        $this->travel(1)->hours();
        for ($i = 0; $i < 10; $i++) {
            Task::create(['title' => 'Nueva '.$i, 'start_date' => today()]);
        }
        $this->travel(1)->hours();
        $old->update(['title' => 'Antigua editada']);

        Livewire::test(TaskList::class)
            ->assertSet('sort', 'updated')
            ->assertViewHas('sorts', fn ($sorts) => $sorts['updated'] === 'Última actualización')
            ->set('perPage', 10)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 11 && $tasks->first()->id === $old->id)
            ->call('setPage', 2)
            ->assertViewHas('tasks', fn ($tasks) => ! $tasks->contains('id', $old->id))
            ->set('sort', 'title')
            ->assertSet('paginators.page', 1)
            ->set('sort', 'invalid')
            ->assertSet('sort', 'updated');
    }

    public function test_explicit_url_sorts_keep_their_existing_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->startOfSecond());
        $first = Task::create(['title' => 'Alfa', 'start_date' => today(), 'priority' => 'urgent-important']);
        $this->travel(1)->hours();
        $second = Task::create(['title' => 'Zeta', 'start_date' => today()->addDay()]);
        $this->travel(1)->hours();
        $first->update(['description' => 'Editada']);

        foreach (['updated' => $first, 'chronological' => $first, 'priority' => $first, 'recent' => $second, 'title' => $first] as $sort => $expected) {
            Livewire::withQueryParams(['sort' => $sort])->test(TaskList::class)
                ->assertSet('sort', $sort)
                ->assertViewHas('tasks', fn ($tasks) => $tasks->first()->id === $expected->id);
        }
    }

    public function test_equal_update_times_use_the_existing_chronological_tiebreaker(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->startOfSecond());
        $later = Task::create(['title' => 'Posterior', 'start_date' => today()->addDay()]);
        $earlier = Task::create(['title' => 'Anterior', 'start_date' => today()]);

        Livewire::test(TaskList::class)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$earlier->id, $later->id]);
    }
}
