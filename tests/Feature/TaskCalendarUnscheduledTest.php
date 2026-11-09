<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskCalendar;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCalendarUnscheduledTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_scoped_and_excludes_scheduled_and_completed_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $category = TaskCategory::create(['name' => 'Ciencia', 'key' => 'ciencia', 'color' => 'blue']);
        $user->tasks()->create(['title' => 'Visible', 'category' => $category->key]);
        $user->tasks()->create(['title' => 'Agendada', 'category' => $category->key, 'start_date' => '2026-11-04']);
        $user->tasks()->create(['title' => 'Terminada', 'category' => $category->key, 'completed' => true]);
        $other->tasks()->create(['title' => 'Ciencia ajena']);
        Livewire::test(TaskCalendar::class)->set('unscheduledSearch', 'Ciencia')
            ->assertSee('Visible')->assertDontSee('Agendada')->assertDontSee('Terminada')->assertDontSee('Ciencia ajena');
    }
}
