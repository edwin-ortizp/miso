<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskList;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskListCategoryStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles: la semana va del lunes 9 al domingo 15.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        TaskCategory::create(['name' => 'Trabajo', 'key' => 'work', 'color' => 'blue']);
        TaskCategory::create(['name' => 'Personal', 'key' => 'personal', 'color' => 'green']);
    }

    public function test_hours_and_counts_by_category_for_today_and_week(): void
    {
        // Hoy, trabajo: intervalo de 90 min.
        Task::create(['title' => 'A', 'category' => 'work', 'size' => 'M',
            'start_date' => '2026-11-11 09:00', 'end_date' => '2026-11-11 10:30', 'start_is_date' => false, 'end_is_date' => false]);
        // Hoy, trabajo: solo estimado, completada.
        Task::create(['title' => 'B', 'category' => 'work', 'size' => 'M', 'estimated_time' => 30,
            'start_date' => '2026-11-11 00:00', 'start_is_date' => true, 'completed' => true, 'completed_at' => '2026-11-11 11:00']);
        // Hoy, personal: sin duración.
        Task::create(['title' => 'C', 'category' => 'personal', 'size' => 'M',
            'start_date' => '2026-11-11 00:00', 'start_is_date' => true]);
        // Lunes de esta semana, personal: 60 min estimados.
        Task::create(['title' => 'D', 'category' => 'personal', 'size' => 'M', 'estimated_time' => 60,
            'start_date' => '2026-11-09 00:00', 'start_is_date' => true]);
        // Semana pasada: no cuenta.
        Task::create(['title' => 'E', 'category' => 'work', 'size' => 'M', 'estimated_time' => 300,
            'start_date' => '2026-11-02 00:00', 'start_is_date' => true]);

        Livewire::test(TaskList::class)
            ->assertViewHas('todayStats', function (array $s) {
                $work = collect($s['rows'])->firstWhere('key', 'work');
                $personal = collect($s['rows'])->firstWhere('key', 'personal');

                return $s['count'] === 3 && $s['minutes'] === 120 && $s['unestimated'] === 1
                    && $work['count'] === 2 && $work['done'] === 1 && $work['minutes'] === 120
                    && $personal['minutes'] === 0 && $s['rows'][0]['key'] === 'work';
            })
            ->assertViewHas('weekStats', function (array $s) {
                $personal = collect($s['rows'])->firstWhere('key', 'personal');

                return $s['count'] === 4 && $s['minutes'] === 180 && $personal['count'] === 2 && $personal['minutes'] === 60;
            })
            ->assertSee('Hoy por categoría')
            ->assertSee('Esta semana')
            ->assertSee('2 h');
    }
}
