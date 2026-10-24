<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskList;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskOverdueTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadlines_agree_between_model_query_chip_filter_and_count(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(8, 47));
        $this->actingAs($user = User::factory()->create());
        $cases = [
            ['Yesterday', '2026-10-23 00:00:00', null, false, true],
            ['Today', '2026-10-24 00:00:00', null, false, false],
            ['Tomorrow', '2026-10-25 00:00:00', null, false, false],
            ['Earlier', null, '2026-10-24 08:46:59', false, true],
            ['Exact', null, '2026-10-24 08:47:00', false, false],
            ['Later', null, '2026-10-24 08:47:01', false, false],
            ['End wins', '2026-10-23 00:00:00', '2026-10-24 00:00:00', false, false],
            ['End expired', '2026-10-25 00:00:00', '2026-10-23 00:00:00', false, true],
            ['Completed', '2026-10-23 00:00:00', null, true, false],
            ['Undated', null, null, false, false],
        ];
        $expected = [];
        foreach ($cases as [$title, $start, $end, $completed, $overdue]) {
            $task = Task::create(['title' => $title, 'start_date' => $start, 'end_date' => $end, 'completed' => $completed]);
            $this->assertSame($overdue, $task->isOverdue(), $title);
            $this->assertSame($overdue, Task::query()->whereKey($task->id)->overdue()->exists(), $title);
            $component = Livewire::test(TaskList::class)->set('filter', [])->set('search', $title);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$component->html());
            $xpath = new \DOMXPath($dom);
            $chips = $xpath->query('//*[@*[name()="wire:key"]="task-row-'.$task->id.'"]//span[contains(normalize-space(.), "Vencida")]');
            $this->assertSame($overdue ? 1 : 0, $chips->length, $title);
            if ($overdue) {
                $expected[] = $task->id;
            }
        }
        $other = User::factory()->create();
        $other->tasks()->create(['title' => 'Foreign overdue', 'end_date' => '2026-10-23 00:00:00']);
        $this->assertEqualsCanonicalizing($expected, Task::query()->overdue()->pluck('id')->all());
        Livewire::test(TaskList::class)
            ->assertViewHas('overdueCount', count($expected))
            ->set('dateFilter', 'vencidas')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === count($expected)
                && $tasks->pluck('id')->sort()->values()->all() === collect($expected)->sort()->values()->all());

        $today = Task::where('title', 'Today')->firstOrFail();
        $this->travelTo(now()->setTime(23, 59, 59));
        $this->assertFalse($today->isOverdue());
        $this->assertFalse(Task::whereKey($today->id)->overdue()->exists());
        $this->travelTo(now()->addSecond());
        $this->assertTrue($today->isOverdue());
        $this->assertTrue(Task::whereKey($today->id)->overdue()->exists());
    }
}
