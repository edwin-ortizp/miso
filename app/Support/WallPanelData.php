<?php

namespace App\Support;

use App\Livewire\Home\Dashboard;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\Goal;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\MealPlanEntry;
use App\Models\ModuleSetting;
use App\Models\MoodEntry;
use App\Models\NegativeHabitDefinition;
use App\Models\NegativeHabitLog;
use App\Models\Plan;
use App\Models\PomodoroSession;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\ShoppingItem;
use App\Models\Task;
use Carbon\Carbon;

/** Read model for the wall panel. All entry points require an authenticated user. */
class WallPanelData
{
    public function forToday(): array
    {
        abort_unless(auth()->check(), 403);
        $today = today();
        $end = $today->copy()->addDays(4)->endOfDay();
        $tasks = Task::with('status')->where(fn ($q) => $q
            ->whereRaw('COALESCE(start_date, end_date) <= ?', [$end])
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$today]))->get();
        $plans = Plan::with('relationships')->where('status', '!=', 'archived')
            ->whereDate('scheduled_on', '<=', $end)->whereRaw('COALESCE(ends_on, scheduled_on) >= ?', [$today->toDateString()])->get();
        $meals = MealPlanEntry::with(['items.recipe', 'items.preparation.recipe'])->whereDate('date', '>=', $today->toDateString())->whereDate('date', '<=', $end->toDateString())->get();
        $people = Relationship::active()->withBirthday()->get();
        $events = RelationshipEvent::active()->visibleGlobally()->with('relationship')
            ->whereRaw('COALESCE(starts_on, event_date) <= ?', [$end->toDateString()])
            ->whereRaw('COALESCE(ends_on, starts_on, event_date) >= ?', [$today->toDateString()])->get();
        $week = [];
        for ($i = 0; $i < 5; $i++) {
            $day = $today->copy()->addDays($i);
            $rows = [];
            foreach ($tasks as $task) {
                $start = $task->start_date ?? $task->end_date;
                $finish = $task->end_date ?? $start;
                if ($start->toDateString() > $day->toDateString() || $finish->toDateString() < $day->toDateString()) {
                    continue;
                }
                $allDay = $task->start_date ? $task->start_is_date : $task->end_is_date;
                $time = ! $allDay && $start->isSameDay($day) ? $start->format('H:i') : null;
                $rows[] = $this->row('task', $task->id, $task->title, $time, $task->completed);
            }
            foreach ($plans as $plan) {
                if ($plan->scheduled_on->lte($day) && ($plan->ends_on ?? $plan->scheduled_on)->gte($day)) {
                    $rows[] = $this->row('plan', $plan->id, $plan->title, null, $plan->status === 'done');
                }
            }
            foreach ($people as $person) {
                if ($person->birthday()?->occurrenceIn($day->year)->isSameDay($day)) {
                    $rows[] = $this->row('person', $person->id, 'Cumpleaños · '.$person->displayName());
                }
            }
            foreach ($events as $event) {
                $date = $event->date();
                if ($date->startsOn->lte($day) && $date->endsOn->gte($day)) {
                    $rows[] = $this->row('person', $event->relationship_id, $event->title.' · '.$event->relationship?->displayName().' · '.$event->dateLabel());
                }
            }
            foreach ($meals->filter(fn ($meal) => $meal->date->isSameDay($day)) as $meal) {
                $title = $meal->items->map(fn ($item) => $item->recipe?->name ?? $item->preparation?->recipe?->name ?? $item->name)->filter()->implode(', ');
                $rows[] = $this->row('meal', (string) $meal->id, (Dashboard::MEAL_TYPES[$meal->meal_type] ?? $meal->meal_type).' · '.$title, null, $meal->status === 'consumed');
            }
            $week[] = ['date' => $day, 'rows' => collect($rows)->sortBy(fn ($r) => ($r['done'] ? '2' : '1').($r['time'] ?? '99:99'))->values()->all()];
        }
        $habits = HabitDefinition::orderBy('base_time')->get();
        $completions = HabitCompletion::whereDate('date', $today)->where('completed', true)->pluck('habit_id')->all();
        $habitStreaks = HabitCompletion::where('completed', true)->whereDate('date', '<=', $today)
            ->get(['habit_id', 'date'])->groupBy('habit_id')->map(fn ($history) => HabitProgress::currentStreak(
                $history->mapWithKeys(fn ($entry) => [$entry->date->toDateString() => true])->all(), $today,
            ))->all();
        $block = match (true) {
            now()->hour >= 6 && now()->hour < 12 => 'morning', now()->hour >= 12 && now()->hour < 18 => 'afternoon', default => 'night'
        };
        $timeline = collect($week[0]['rows'])->whereNotNull('time');
        foreach ($habits->whereNotNull('base_time') as $habit) {
            if ($habit->base_time) {
                $timeline->push($this->row('habit', (string) $habit->id, $habit->name, substr($habit->base_time, 0, 5), in_array($habit->id, $completions)));
            }
        }
        $settings = ModuleSetting::where('module', 'pomodoro')->value('settings') ?? [];
        $negativeHabits = NegativeHabitDefinition::orderBy('name')->get();
        $lastRelapses = NegativeHabitLog::selectRaw('habit_id, MAX(timestamp) as last_at')->groupBy('habit_id')->pluck('last_at', 'habit_id');
        $moods = MoodEntry::with('moodState')->whereDate('date', '>=', $today->copy()->subDays(6)->toDateString())->whereDate('date', '<=', $today->toDateString())->orderBy('timestamp')->get();
        $energies = EnergyEntry::whereDate('date', '>=', $today->copy()->subDays(6)->toDateString())->whereDate('date', '<=', $today->toDateString())->orderBy('timestamp')->get();
        $trend = collect(range(6, 0))->map(function ($offset) use ($today, $moods, $energies) {
            $date = $today->copy()->subDays($offset);

            return ['date' => $date, 'mood' => $moods->filter(fn ($e) => Carbon::parse($e->date)->isSameDay($date))->last(),
                'energy' => $energies->filter(fn ($e) => Carbon::parse($e->date)->isSameDay($date))->last()?->level];
        });

        return compact('week', 'habits', 'completions', 'habitStreaks', 'block', 'negativeHabits', 'lastRelapses', 'trend') + [
            'timeline' => $timeline->sortBy('time')->values()->all(),
            'currentTask' => $tasks->first(fn ($task) => ! $task->completed && $task->start_date && ! $task->start_is_date
                && $task->start_date->lte(now()) && ($task->end_date ?? $task->start_date)->gte(now())),
            'nextTasks' => collect($week[0]['rows'])->where('kind', 'task')->where('done', false)->whereNotNull('time')->filter(fn ($r) => $r['time'] >= now()->format('H:i'))->values(),
            'waterTotal' => DrinkLog::whereDate('date', $today)->sum('hydration_value'),
            'waterLogs' => DrinkLog::whereDate('date', $today)->orderByDesc('timestamp')->limit(8)->get(),
            'waterGoal' => WaterGoal::forUser(auth()->user()),
            'drinkTypes' => DrinkType::orderBy('name')->get(),
            'focusMinutes' => (int) floor(PomodoroSession::whereDate('date', $today)->where('completed', true)->sum('duration') / 60),
            'focusGoal' => (int) ($settings[$today->isWeekend() ? 'weekend_goal_minutes' : 'weekday_goal_minutes'] ?? ($today->isWeekend() ? 120 : 300)),
            'goals' => Goal::where('status', 'active')->orderBy('due_date')->get(),
            'shopping' => ShoppingItem::where('next_purchase', true)->orderBy('name')->get(),
        ];
    }

    private function row(string $kind, string $id, string $title, ?string $time = null, bool $done = false): array
    {
        return compact('kind', 'id', 'title', 'time', 'done');
    }
}
