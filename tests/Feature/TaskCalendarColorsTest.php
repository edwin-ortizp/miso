<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskCalendar;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCalendarColorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_use_the_owners_catalog_color_and_a_safe_neutral_fallback(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = TaskCategory::create(['name' => 'Trabajo', 'key' => 'trabajo', 'color' => 'blue']);
        $task = $user->tasks()->create(['title' => 'Con color', 'category' => $category->key, 'start_date' => '2026-11-04']);
        $none = $user->tasks()->create(['title' => 'Neutral', 'start_date' => '2026-11-04']);
        $events = collect(Livewire::test(TaskCalendar::class)->instance()->events('2026-11-01', '2026-12-01'));
        $this->assertSame('blue', $events->firstWhere('id', $task->id)['extendedProps']['categoryColor']);
        $this->assertNull($events->firstWhere('id', $none->id)['extendedProps']['categoryColor']);
    }
}
