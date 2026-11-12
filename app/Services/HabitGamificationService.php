<?php

namespace App\Services;

use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Services\Game\RewardGranter;
use App\Support\HabitProgress;
use App\Support\CompanionVoice;
use App\Support\Habits\HabitActionRunner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HabitGamificationService
{
    public function __construct(private readonly HabitActionRunner $actions) {}

    /**
     * Toggle a habit and return the single highest-priority feedback message.
     *
     * Las claves de acción (requiresInput, promptFields, actionResult) van siempre
     * presentes: este array se reparte con `dispatch('habit-feedback', ...)`.
     *
     * @return array{kind: string, tone: string, title: string, message: string, icon: string, date: string, habitId: int, period: ?string, streak: int, requiresInput: bool, promptFields: array, actionResult: ?string}
     */
    public function toggle(int $habitId, string $date): array
    {
        $selected = Carbon::parse($date, config('app.timezone'))->startOfDay();

        [$habit, $completion, $isCompleting] = DB::transaction(function () use ($habitId, $selected): array {
            $habit = HabitDefinition::query()->findOrFail($habitId);
            $completion = HabitCompletion::query()
                ->where('habit_id', $habit->id)
                ->whereDate('date', $selected->toDateString())
                ->first();
            $isCompleting = ! $completion?->completed;

            if ($completion) {
                $completion->update(['completed' => $isCompleting]);
            } else {
                $completion = HabitCompletion::create([
                    'habit_id' => $habit->id,
                    'date' => $selected->toDateString(),
                    'completed' => true,
                ]);
            }

            return [$habit, $completion, $isCompleting];
        });

        // Fuera de la transacción: un fallo aquí no debe deshacer el completado.
        if ($isCompleting && $this->periodIsComplete($habit, $selected)) {
            app(RewardGranter::class)->forHabitGroup($completion, $habit->time_of_day ?: 'anytime');
        }

        $action = $isCompleting
            ? $this->actions->afterComplete($habit, $completion, $selected)
            : array_merge(HabitActionRunner::EMPTY, ['actionResult' => $this->actions->afterUncomplete($completion)]);

        return $this->feedbackFor($habit, $selected, $isCompleting) + $action;
    }

    /**
     * Segundo paso del modo «preguntar al completar»: el usuario ya respondió.
     *
     * @param  array<string, mixed>  $input
     * @return array{actionResult: ?string}
     */
    public function resolveAction(int $habitId, string $date, array $input): array
    {
        $selected = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $habit = HabitDefinition::query()->findOrFail($habitId);
        $completion = HabitCompletion::query()
            ->where('habit_id', $habit->id)
            ->whereDate('date', $selected->toDateString())
            ->firstOrFail();

        return ['actionResult' => $this->actions->resolve($habit, $completion, $selected, $input)];
    }

    /** ¿Quedaron completos, ese día, todos los hábitos del momento del día de este hábito? */
    private function periodIsComplete(HabitDefinition $habit, Carbon $selected): bool
    {
        $period = $habit->time_of_day ?: 'anytime';
        $periodIds = HabitDefinition::query()->get(['id', 'time_of_day'])
            ->filter(fn (HabitDefinition $candidate) => ($candidate->time_of_day ?: 'anytime') === $period)
            ->pluck('id')->map(fn ($id) => (int) $id);
        $completedIds = HabitCompletion::query()
            ->whereDate('date', $selected->toDateString())
            ->where('completed', true)
            ->pluck('habit_id')->map(fn ($id) => (int) $id);

        return $periodIds->isNotEmpty() && $periodIds->diff($completedIds)->isEmpty();
    }

    /** @return array<string, mixed> El mensaje de gamificación de mayor prioridad. */
    private function feedbackFor(HabitDefinition $habit, Carbon $selected, bool $isCompleting): array
    {
        if (! $selected->isToday()) {
            return $this->feedback(
                kind: 'history',
                tone: 'neutral',
                title: 'Registro actualizado',
                message: $isCompleting ? "Marcaste {$habit->name} como completado." : "Marcaste {$habit->name} como pendiente.",
                icon: 'bi-calendar-check',
                selected: $selected,
                habit: $habit,
            );
        }

        if (! $isCompleting) {
            return $this->feedback(
                kind: 'habit',
                tone: 'neutral',
                title: 'Hábito pendiente',
                message: "Actualizamos {$habit->name}. Puedes retomarlo cuando tenga sentido.",
                icon: 'bi-arrow-counterclockwise',
                selected: $selected,
                habit: $habit,
            );
        }

        $habits = HabitDefinition::query()->orderBy('base_time')->orderBy('id')->get();
        $completedIds = HabitCompletion::query()
            ->whereDate('date', $selected->toDateString())
            ->where('completed', true)
            ->pluck('habit_id')
            ->map(fn ($id) => (int) $id);
        $period = $habit->time_of_day ?: 'anytime';

        if ($habits->isNotEmpty() && $completedIds->count() === $habits->count()) {
            return $this->feedback(
                kind: 'day',
                tone: 'celebration',
                title: 'Hábitos de hoy completos',
                message: $this->message('day', $selected, $habit),
                icon: 'bi-stars',
                selected: $selected,
                habit: $habit,
                period: $period,
            );
        }

        $periodHabits = $habits->filter(
            fn (HabitDefinition $candidate) => ($candidate->time_of_day ?: 'anytime') === $period,
        );
        $periodIsComplete = $periodHabits->isNotEmpty()
            && $periodHabits->every(fn (HabitDefinition $candidate) => $completedIds->contains($candidate->id));

        if ($periodIsComplete) {
            $periodConfig = config("habit_gamification.periods.{$period}", config('habit_gamification.periods.anytime'));

            return $this->feedback(
                kind: 'period',
                tone: 'success',
                title: $periodConfig['title'],
                message: $this->message('period', $selected, $habit, $period),
                icon: $periodConfig['icon'],
                selected: $selected,
                habit: $habit,
                period: $period,
            );
        }

        $streak = $this->streakFor($habit, $selected);
        if (in_array($streak, config('habit_gamification.streak_milestones', []), true)) {
            return $this->feedback(
                kind: 'streak',
                tone: 'support',
                title: "Racha de {$streak} días",
                message: $this->message('streak', $selected, $habit, $period, $streak),
                icon: 'bi-fire',
                selected: $selected,
                habit: $habit,
                period: $period,
                streak: $streak,
            );
        }

        return $this->feedback(
            kind: 'habit',
            tone: 'success',
            title: 'Hábito completado',
            message: $this->message('habit', $selected, $habit, $period),
            icon: 'bi-check2-circle',
            selected: $selected,
            habit: $habit,
            period: $period,
            streak: $streak,
        );
    }

    /**
     * @param  Collection<int, HabitDefinition>  $habits
     * @param  array<int, bool>  $completions
     * @param  array<string, mixed>|null  $lastFeedback
     * @return array{state: string, tone: string, eyebrow: string, title: string, message: string, icon: string, suggestedHabitId: ?int}
     */
    public function coachCard(Carbon $selected, Collection $habits, array $completions, ?array $lastFeedback = null): array
    {
        if ($habits->isEmpty()) {
            return $this->coach('empty', 'neutral', 'Tu ritual', 'Elige tu primer hábito', 'Cuando definas un hábito, este espacio te ayudará a mantenerlo presente.', 'bi-stars');
        }

        $completedIds = collect($completions)->filter()->keys()->map(fn ($id) => (int) $id);
        $completedCount = $completedIds->count();

        if (! $selected->isToday()) {
            return $this->coach(
                'history',
                'neutral',
                'Mirada retrospectiva',
                "{$completedCount} de {$habits->count()} completados",
                'Puedes ajustar este día con calma; las celebraciones se reservan para el presente.',
                'bi-calendar3',
            );
        }

        if ($completedCount === $habits->count()) {
            return $this->coach(
                'complete',
                'celebration',
                'Ritual completo',
                'Hábitos de hoy completos',
                ($lastFeedback['kind'] ?? null) === 'day' && ($lastFeedback['date'] ?? null) === $selected->toDateString()
                    ? $lastFeedback['message'] : $this->message('complete', $selected),
                'bi-stars',
            );
        }

        if (($lastFeedback['kind'] ?? null) === 'period' && ($lastFeedback['date'] ?? null) === $selected->toDateString()) {
            return $this->coach(
                'period',
                'success',
                'Una etapa cerrada',
                $lastFeedback['title'],
                $lastFeedback['message'],
                $lastFeedback['icon'],
            );
        }

        $incomplete = $habits->reject(fn (HabitDefinition $habit) => $completedIds->contains($habit->id))->values();
        $suggested = $this->suggestedHabit($incomplete);
        $yesterday = $selected->copy()->subDay();
        $yesterdayHabits = $habits->filter(
            fn (HabitDefinition $habit) => $habit->created_at?->lte($yesterday->copy()->endOfDay()) ?? false,
        );
        $yesterdayCompleted = HabitCompletion::query()
            ->whereDate('date', $yesterday->toDateString())
            ->where('completed', true)
            ->whereIn('habit_id', $yesterdayHabits->pluck('id'))
            ->pluck('habit_id')
            ->unique()
            ->count();
        $missedYesterday = max($yesterdayHabits->count() - $yesterdayCompleted, 0);

        if ($missedYesterday > 0 && $suggested) {
            $group = $completedCount > 0 ? 'recovery_started' : 'recovery';

            return $this->coach(
                'recovery',
                'support',
                'Volver también cuenta',
                $completedCount > 0 ? 'Ya retomaste el movimiento' : 'Hoy es una nueva oportunidad',
                $this->message($group, $selected, $suggested, $suggested->time_of_day ?: 'anytime', 0, [
                    '{missed}' => (string) $missedYesterday,
                ]),
                'bi-arrow-up-right-circle',
                $suggested->id,
            );
        }

        if ($suggested) {
            $group = $completedCount === 0 ? 'start' : 'next';

            return $this->coach(
                'next',
                'success',
                $completedCount === 0 ? 'Empieza a tu ritmo' : 'Siguiente paso',
                $completedCount === 0 ? 'Una acción es suficiente para comenzar' : "Llevas {$completedCount} de {$habits->count()}",
                $this->message($group, $selected, $suggested),
                'bi-compass',
                $suggested->id,
            );
        }

        return $this->coach('next', 'neutral', 'Tu ritual', 'Sigue a tu ritmo', 'Cada registro ayuda a hacer visible tu constancia.', 'bi-compass');
    }

    private function streakFor(HabitDefinition $habit, Carbon $selected): int
    {
        $dates = HabitCompletion::query()
            ->where('habit_id', $habit->id)
            ->where('completed', true)
            ->whereDate('date', '<=', $selected->toDateString())
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [Carbon::parse($date)->toDateString() => true])
            ->all();

        return HabitProgress::currentStreak($dates, $selected);
    }

    /** @param array<string, string> $replacements */
    private function message(
        string $group,
        Carbon $selected,
        ?HabitDefinition $habit = null,
        ?string $period = null,
        int $streak = 0,
        array $replacements = [],
    ): string {
        $voiceGroup = ['habit' => 'B', 'period' => 'C', 'day' => 'D'][$group] ?? null;
        if ($voiceGroup && \App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'habits')) {
            return CompanionVoice::pick((int) auth()->id(), $voiceGroup, [
                'habit' => $habit?->name ?? 'tu rutina',
                'period' => config("habit_gamification.periods.{$period}.label", 'rutina'),
            ])['text'];
        }

        $messages = config("habit_gamification.messages.{$group}", []);
        if ($messages === []) {
            return 'Tu progreso quedó registrado.';
        }

        $key = implode('|', [auth()->id(), $selected->toDateString(), $group, $habit?->id, $period, $streak]);
        $index = hexdec(substr(hash('sha256', $key), 0, 8)) % count($messages);
        $periodLabel = config("habit_gamification.periods.{$period}.label", 'rutina');

        return strtr($messages[$index], array_merge([
            '{habit}' => $habit?->name ?? 'tu rutina',
            '{period}' => $periodLabel,
            '{streak}' => (string) $streak,
        ], $replacements));
    }

    /** @param Collection<int, HabitDefinition> $incomplete */
    private function suggestedHabit(Collection $incomplete): ?HabitDefinition
    {
        $currentPeriod = $this->currentPeriod();

        return $incomplete->first(fn (HabitDefinition $habit) => $habit->time_of_day === $currentPeriod)
            ?? $incomplete->first(fn (HabitDefinition $habit) => ($habit->time_of_day ?: 'anytime') === 'anytime')
            ?? $incomplete->first();
    }

    private function currentPeriod(): string
    {
        $hour = Carbon::now(config('app.timezone'))->hour;

        return match (true) {
            $hour >= 6 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 18 => 'afternoon',
            default => 'night',
        };
    }

    /** @return array{kind: string, tone: string, title: string, message: string, icon: string, date: string, habitId: int, period: ?string, streak: int} */
    private function feedback(
        string $kind,
        string $tone,
        string $title,
        string $message,
        string $icon,
        Carbon $selected,
        HabitDefinition $habit,
        ?string $period = null,
        int $streak = 0,
    ): array {
        return compact('kind', 'tone', 'title', 'message', 'icon', 'period', 'streak') + [
            'date' => $selected->toDateString(),
            'habitId' => $habit->id,
        ];
    }

    /** @return array{state: string, tone: string, eyebrow: string, title: string, message: string, icon: string, suggestedHabitId: ?int} */
    private function coach(
        string $state,
        string $tone,
        string $eyebrow,
        string $title,
        string $message,
        string $icon,
        ?int $suggestedHabitId = null,
    ): array {
        return compact('state', 'tone', 'eyebrow', 'title', 'message', 'icon', 'suggestedHabitId');
    }
}
