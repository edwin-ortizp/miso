<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskList;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskListFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_panel_does_not_contain_the_header_menu_or_divider(): void
    {
        $this->actingAs(User::factory()->create());
        $html = Livewire::test(TaskList::class)->html();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//*[@id="task-list-filters"]//*[contains(@class, "md-mcard__divider")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="task-list-filters"]//button[contains(@class, "md-menu__trigger")]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class, "md-mcard__tools")]//button[contains(@class, "md-menu__trigger")]')->length);
        $this->assertSame(2, $xpath->query('//*[@id="task-list-filters"]//select[@name="priorityFilter" or @name="sizeFilter"]')->length);
    }

    public function test_status_selection_defaults_clears_and_combines(): void
    {
        $this->actingAs($user = User::factory()->create());
        $statuses = TaskStatus::ensureFor($user->id);
        foreach ($statuses as $status) {
            Task::create(['title' => 'Task '.$status->name, 'status_id' => $status->id, 'priority' => 'urgent-important', 'size' => 'M']);
        }
        $pending = $statuses->where('type', '!=', TaskStatus::DONE)->pluck('id')->all();
        $ready = $statuses->firstWhere('type', TaskStatus::READY);
        $done = $statuses->firstWhere('type', TaskStatus::DONE);
        $component = Livewire::test(TaskList::class)
            ->assertSet('filter', $pending)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 3)
            ->set('filter', [$ready->id, $done->id])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 2)
            ->set('priorityFilter', 'urgent-important')->set('sizeFilter', 'M')
            ->set('dateFilter', ['sin-fecha'])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 2)
            ->set('sizeFilter', 'XS')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 0)
            ->call('clearFilters')->assertSet('filter', $pending)
            ->assertSet('sizeFilter', '')->assertSet('priorityFilter', '')->assertSet('dateFilter', [])
            ->set('filter', [$done->id])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1 && $tasks->first()->completed)
            ->set('filter', [])->assertSet('statusQuery', 'all')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 4);
        $component->call('setPage', 2)->set('filter', [$ready->id])->assertSet('paginators.page', 1);
    }

    public function test_status_urls_accept_legacy_values_and_arrays(): void
    {
        $this->actingAs($user = User::factory()->create());
        $statuses = TaskStatus::ensureFor($user->id);
        foreach (['pending', 'completed', 'all'] as $legacy) {
            $expected = match ($legacy) {
                'pending' => $statuses->where('type', '!=', TaskStatus::DONE)->pluck('id')->all(),
                'completed' => $statuses->where('type', TaskStatus::DONE)->pluck('id')->all(),
                'all' => [],
            };
            Livewire::withQueryParams(['status' => $legacy])->test(TaskList::class)->assertSet('filter', $expected);
        }
        $ids = $statuses->take(2)->pluck('id')->all();
        Livewire::withQueryParams(['status' => $ids])->test(TaskList::class)->assertSet('filter', $ids);
        Livewire::withQueryParams(['status' => ''])->test(TaskList::class)->assertSet('filter', []);
    }

    public function test_date_filter_combines_several_options(): void
    {
        $this->actingAs($user = User::factory()->create());
        $ready = TaskStatus::firstOfType($user->id, TaskStatus::READY);
        foreach ([
            'Hoy' => [now()->startOfDay()->addHours(23), now()->endOfDay()],
            'Vencida' => [now()->subDays(3), now()->subDays(2)],
            'Próxima' => [now()->addDays(2), now()->addDays(3)],
            'Sin fecha' => [null, null],
        ] as $title => [$start, $end]) {
            Task::create(['title' => $title, 'status_id' => $ready->id, 'start_date' => $start, 'end_date' => $end]);
        }
        $titles = fn ($tasks) => $tasks->pluck('title')->sort()->values()->all();

        Livewire::test(TaskList::class)
            ->set('dateFilter', ['hoy', 'vencidas', 'sin-fecha'])
            ->assertViewHas('tasks', fn ($tasks) => $titles($tasks) === ['Hoy', 'Sin fecha', 'Vencida'])
            ->assertSee('Cuándo: Vencidas')
            ->call('removeFilter', 'date', 'vencidas')
            ->assertSet('dateFilter', ['hoy', 'sin-fecha'])
            ->assertViewHas('tasks', fn ($tasks) => $titles($tasks) === ['Hoy', 'Sin fecha'])
            ->set('dateFilter', ['proximas', 'invalida'])
            ->assertSet('dateFilter', ['proximas']);

        Livewire::withQueryParams(['date' => 'hoy'])->test(TaskList::class)->assertSet('dateFilter', ['hoy']);
    }

    public function test_status_options_and_filter_ids_are_user_scoped(): void
    {
        $other = User::factory()->create();
        $foreign = TaskStatus::firstOfType($other->id, TaskStatus::READY);
        $this->actingAs($user = User::factory()->create());
        $own = TaskStatus::firstOfType($user->id, TaskStatus::READY);
        Task::create(['title' => 'Owned task', 'status_id' => $own->id]);
        $other->tasks()->create(['title' => 'Foreign task', 'status_id' => $foreign->id]);
        Livewire::test(TaskList::class)
            ->assertViewHas('statusOptions', fn ($options) => isset($options[$own->id]) && ! isset($options[$foreign->id]))
            ->set('filter', [$own->id, $foreign->id, 'invalid', $own->id])
            ->assertSet('filter', [$own->id])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1 && $tasks->first()->user_id === $user->id)
            ->set('filter', [$foreign->id])->assertSet('filter', [])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1);
    }
}
