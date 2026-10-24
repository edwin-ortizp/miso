<?php

namespace App\Livewire\Plan\Concerns;

use App\Actions\RecordPlanVisit;
use App\Actions\SavePlan;
use App\Models\Circle;
use App\Models\Plan;
use App\Models\PlanCategory;
use App\Models\PlanTag;
use App\Models\PlanVisit;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Create/edit a plan and record that it was done. Shared by the module list,
 * the plan detail and the Planes tab of a relationship.
 */
trait ManagesPlanDialogs
{
    use \App\Livewire\Concerns\HasReminderDraft;
    public bool $showPlanForm = false;

    public ?string $editingPlanId = null;

    public string $planTitle = '';

    public string $planType = 'restaurant';

    public string $planCategory = '';

    public string $planCategoryId = '';

    public string $planCity = '';

    public string $planAddress = '';

    public ?string $planScheduledOn = null;

    public ?string $planEndsOn = null;

    public string $planNotes = '';

    public array $planCircles = [];

    public array $planPeople = [];

    public array $planTags = [];

    public array $planLinks = [];

    /** Image URLs in order; the first one is the main image. */
    public array $planImages = [];

    public bool $showVisitForm = false;

    public ?string $visitPlanId = null;

    public ?string $visitDate = null;

    public array $visitPeople = [];

    public string $visitComment = '';

    /** People preselected when the dialogs open from a relationship. */
    protected function defaultPlanPeople(): array
    {
        return [];
    }

    public function updatedPlanCategoryId(): void
    {
        $this->planCategory = '';
    }

    public function openPlanForm(?string $id = null): void
    {
        $this->resetPlanForm();

        if ($id) {
            $plan = Plan::query()->with(['circles', 'relationships', 'tags', 'links', 'images'])->findOrFail($id);

            $this->editingPlanId = $plan->id;
            $this->loadReminders($plan);
            $this->planTitle = $plan->title;
            $this->planType = $plan->type;
            $this->planCategory = $plan->category ?? '';
            $this->planCategoryId = $plan->plan_category_id ?? '';
            $this->planCity = $plan->city ?? '';
            $this->planAddress = $plan->address ?? '';
            $this->planScheduledOn = $plan->scheduled_on?->toDateString();
            $this->planEndsOn = $plan->ends_on?->toDateString();
            $this->planNotes = $plan->notes ?? '';
            $this->planCircles = $plan->circles->pluck('id')->all();
            $this->planPeople = $plan->relationships->pluck('id')->all();
            $this->planTags = $plan->tags->pluck('id')->all();
            $this->planLinks = $plan->links->map(fn ($link) => ['url' => $link->url, 'label' => $link->label ?? ''])->all();
            $this->planImages = $plan->images->pluck('url')->all();
        } else {
            $this->planPeople = $this->defaultPlanPeople();
        }

        if ($this->planLinks === []) {
            $this->planLinks = [['url' => '', 'label' => '']];
        }

        if ($this->planImages === []) {
            $this->planImages = [''];
        }

        $this->showPlanForm = true;
    }

    public function closePlanForm(): void
    {
        $this->showPlanForm = false;
        $this->resetPlanForm();
    }

    public function addPlanLink(): void
    {
        $this->planLinks[] = ['url' => '', 'label' => ''];
    }

    public function removePlanLink(int $index): void
    {
        unset($this->planLinks[$index]);
        $this->planLinks = array_values($this->planLinks) ?: [['url' => '', 'label' => '']];
    }

    public function addPlanImage(): void
    {
        $this->planImages[] = '';
    }

    public function removePlanImage(int $index): void
    {
        unset($this->planImages[$index]);
        $this->planImages = array_values($this->planImages) ?: [''];
    }

    /** Moving an image to the top makes it the main image. */
    public function movePlanImageUp(int $index): void
    {
        if ($index < 1 || ! isset($this->planImages[$index])) {
            return;
        }

        [$this->planImages[$index - 1], $this->planImages[$index]] = [$this->planImages[$index], $this->planImages[$index - 1]];
    }

    public function savePlan(): void
    {
        \Illuminate\Support\Facades\DB::transaction(fn () => $this->persistPlanWithReminders());
    }

    private function persistPlanWithReminders(): void
    {
        $this->planLinks = collect($this->planLinks)
            ->filter(fn ($link) => trim($link['url'] ?? '') !== '' || trim($link['label'] ?? '') !== '')
            ->values()
            ->all();

        $this->planImages = collect($this->planImages)
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->values()
            ->all();

        $this->validate([
            'planTitle' => ['required', 'string', 'max:160'],
            'planType' => ['required', Rule::in(array_keys(Plan::TYPES))],
            'planCategoryId' => ['nullable', 'uuid', Rule::exists('plan_categories', 'id')->where('user_id', auth()->id())],
            'planCategory' => ['nullable', 'string', 'max:80'],
            'planCity' => ['nullable', 'string', 'max:120'],
            'planAddress' => ['nullable', 'string', 'max:255'],
            'planScheduledOn' => ['nullable', 'date'],
            'planEndsOn' => ['nullable', 'date', 'after_or_equal:planScheduledOn'],
            'planNotes' => ['nullable', 'string', 'max:2000'],
            'planCircles' => ['array'],
            'planCircles.*' => ['string'],
            'planPeople' => ['array'],
            'planPeople.*' => ['string'],
            'planTags' => ['array'],
            'planTags.*' => ['string', Rule::exists('plan_tags', 'id')->where('user_id', auth()->id())],
            'planLinks' => ['array', 'max:10'],
            'planLinks.*.url' => ['required', 'url:http,https', 'max:2048'],
            'planLinks.*.label' => ['nullable', 'string', 'max:120'],
            'planImages' => ['array', 'max:12'],
            'planImages.*' => ['url:http,https', 'max:2048'],
        ], [
            'planImages.*.url' => 'Escribe la URL de una imagen que empiece por http:// o https://.',
            'planLinks.*.url.required' => 'Escribe el enlace o quita la fila.',
            'planLinks.*.url.url' => 'Escribe un enlace válido que empiece por http:// o https://.',
            'planEndsOn.after_or_equal' => 'La fecha final no puede ser anterior a la fecha del plan.',
        ], [
            'planTitle' => 'nombre',
            'planType' => 'tipo',
            'planEndsOn' => 'hasta',
        ]);

        $this->validateReminders($this->planScheduledOn);
        $savedPlan = SavePlan::handle(
            $this->editingPlanId ? Plan::query()->findOrFail($this->editingPlanId) : new Plan,
            [
                'title' => trim($this->planTitle),
                'type' => $this->planType,
                'category' => trim($this->planCategory) ?: null,
                ...($this->planCategoryId !== '' ? ['plan_category_id' => $this->planCategoryId] : []),
                'city' => trim($this->planCity) ?: null,
                'address' => trim($this->planAddress) ?: null,
                'scheduled_on' => $this->planScheduledOn ?: null,
                'ends_on' => $this->planEndsOn ?: null,
                'notes' => trim($this->planNotes) ?: null,
            ],
            $this->planCircles,
            $this->planPeople,
            $this->planLinks,
            $this->planImages,
            $this->planTags,
        );

        $this->saveReminders($savedPlan);

        $this->closePlanForm();
    }

    public function openVisitForm(string $planId): void
    {
        $this->resetVisitForm();
        $this->visitPlanId = Plan::query()->findOrFail($planId)->id;
        $this->visitDate = today()->toDateString();
        $this->visitPeople = $this->defaultPlanPeople();
        $this->showVisitForm = true;
    }

    public function closeVisitForm(): void
    {
        $this->showVisitForm = false;
        $this->resetVisitForm();
    }

    public function saveVisit(): void
    {
        $this->validate([
            'visitDate' => ['required', 'date', 'before_or_equal:today'],
            'visitPeople' => ['required', 'array', 'min:1'],
            'visitPeople.*' => ['string'],
            'visitComment' => ['nullable', 'string', 'max:1000'],
        ], [
            'visitPeople.required' => 'Elige al menos una persona.',
            'visitPeople.min' => 'Elige al menos una persona.',
            'visitDate.before_or_equal' => 'La fecha no puede ser futura.',
        ], [
            'visitDate' => 'fecha',
        ]);

        $people = Relationship::query()->whereKey($this->visitPeople)->pluck('id');

        if ($people->isEmpty()) {
            $this->addError('visitPeople', 'Elige al menos una persona.');

            return;
        }

        RecordPlanVisit::handle(Plan::query()->findOrFail($this->visitPlanId), $this->visitDate, $people, $this->visitComment);

        $this->closeVisitForm();
    }

    public function deleteVisit(string $id): void
    {
        PlanVisit::query()->findOrFail($id)->delete();
    }

    public function deletePlan(string $id): void
    {
        Plan::query()->findOrFail($id)->delete();
    }

    public function setPlanStatus(string $id, string $status): void
    {
        abort_unless(array_key_exists($status, Plan::STATUSES), 422);

        $plan = Plan::query()->findOrFail($id);
        $plan->update(['status' => $status === 'pending' && $plan->scheduled_on?->isFuture() ? 'scheduled' : $status]);
    }

    /** Sorting is done in memory so "never visited" can go last on every database. */
    protected function sortPlans(Collection $plans, string $sort): Collection
    {
        return match ($sort) {
            'stale' => $plans->sortBy([
                fn ($a, $b) => ($a->last_visited_on === null) <=> ($b->last_visited_on === null),
                fn ($a, $b) => (string) $a->last_visited_on <=> (string) $b->last_visited_on,
            ])->values(),
            'popular' => $plans->sortBy([['visits_count', 'desc'], ['title', 'asc']])->values(),
            'upcoming' => $plans->sortBy([
                fn ($a, $b) => ($a->scheduled_on === null) <=> ($b->scheduled_on === null),
                fn ($a, $b) => (string) $a->scheduled_on?->toDateString() <=> (string) $b->scheduled_on?->toDateString(),
            ])->values(),
            'name' => $plans->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            default => $plans->sortByDesc('created_at')->values(),
        };
    }

    protected function surpriseFrom(Builder $query): void
    {
        $plan = $query->whereIn('status', ['pending', 'scheduled'])->inRandomOrder()->first();

        if (! $plan) {
            $this->surpriseMessage = 'No hay planes pendientes con estos filtros.';

            return;
        }

        $this->redirectRoute('plans.show', ['plan' => $plan->id], navigate: true);
    }

    protected function planDialogOptions(): array
    {
        return [
            'planCategoryOptions' => PlanCategory::hierarchyOptions(auth()->id()),
            'typeOptions' => Plan::TYPES,
            'circleOptions' => Circle::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'peopleOptions' => Relationship::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all(),
            'planTagOptions' => PlanTag::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function resetPlanForm(): void
    {
        $this->reminders = [];
        $this->reset(['editingPlanId', 'planTitle', 'planType', 'planCategory', 'planCategoryId', 'planCity', 'planAddress', 'planScheduledOn', 'planEndsOn', 'planNotes', 'planCircles', 'planPeople', 'planTags', 'planLinks', 'planImages']);
        $this->resetValidation();
    }

    private function resetVisitForm(): void
    {
        $this->reset(['visitPlanId', 'visitDate', 'visitPeople', 'visitComment']);
        $this->resetValidation();
    }
}
