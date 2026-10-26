<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\HasUrlDate;
use App\Models\MealPlanEntry;
use App\Models\MealPlanEntryItem;
use App\Models\Recipe;
use App\Models\MealPreparation;
use App\Models\ShoppingItem;
use App\Services\Meal\MealInventory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Comidas')]
class MealWeekly extends Component
{
    use HasUrlDate;

    #[\Livewire\Attributes\Url(as: 'meal')]
    public ?int $detailMeal = null;

    public ?string $formTime = null;
    public ?int $formDuration = null;
    public string $scheduleMessage = '';
    public array $effectiveSchedule = [];

    public bool $showForm = false;
    public string $formDate = '';
    public string $formMealType = '';
    public string $formNotes = '';
    public ?int $formCalories = null;
    public ?int $editingId = null;
    public array $formItems = [];
    public string $formStatus = 'planned';
    public array $consumption = [];
    public string $consumptionMode = '';
    public string $outsideNotes = '';
    public $outsideCalories = null;
    public string $targetDate = '';
    public string $targetMealType = 'cena';
    public string $selectedPreparation = '';
    public bool $showOutside = false;
    public bool $actionOpen = false;
    public string $actionType = 'move';
    #[Locked]
    public string $operationKey = '';

    public const KINDS = ['recipe' => 'Receta', 'preparation' => 'Preparación', 'ingredient' => 'Ingrediente', 'free' => 'Elemento libre'];

    public const INGREDIENT_UNITS = ['g' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'L' => 'L', 'unit' => 'unidad'];

    public array $mealTypes = [
        'desayuno' => 'Desayuno',
        'comida' => 'Onces (mañana)',
        'almuerzo' => 'Almuerzo',
        'merienda' => 'Merienda (tarde)',
        'cena' => 'Cena',
    ];

    public function mount(): void
    {
        $this->initializeSelectedDate();
        if ($this->detailMeal !== null) {
            $entry = MealPlanEntry::where('user_id', auth()->id())->findOrFail($this->detailMeal);
            $this->selectedDate = $entry->date->toDateString();
            $this->openForm($this->selectedDate, $entry->meal_type);
        }
    }

    public function previousWeek(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function openForm(string $date, string $mealType): void
    {
        abort_unless(array_key_exists($mealType, $this->mealTypes), 404);
        $this->resetValidation();

        $existing = MealPlanEntry::where('user_id', auth()->id())->with(['items.recipe', 'items.preparation'])
            ->whereDate('date', $date)
            ->where('meal_type', $mealType)
            ->first();

        $this->editingId = $existing?->id;
        $this->formTime = $existing?->scheduled_time ? substr($existing->scheduled_time, 0, 5) : null;
        $this->formDuration = $existing?->duration_minutes;
        $this->scheduleMessage = '';
        $profiles = app(\App\Services\Meal\MealSchedule::class)->profiles((int) auth()->id());
        $this->effectiveSchedule = $profiles[Carbon::parse($date)->isWeekend() ? 'weekend' : 'weekday'][$mealType];
        $this->formStatus = $existing?->status ?? 'planned';
        $this->consumption = $existing?->consumption ?? [];
        $this->consumptionMode = $existing?->consumption_mode ?? '';
        $this->outsideNotes = '';
        $this->outsideCalories = null;
        $this->showOutside = false;
        $this->operationKey = (string) str()->uuid();
        $this->targetDate = $date;
        $this->targetMealType = $mealType;
        $this->selectedPreparation = '';
        $this->formNotes = $existing?->notes ?? '';
        $this->formCalories = $existing?->calories;
        $this->formItems = $existing?->items->map(fn ($item) => $this->rowFromItem($item))->values()->toArray() ?? [];
        $this->formDate = $date;
        $this->formMealType = $mealType;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->detailMeal = null;
        $this->editingId = null;
        $this->resetValidation();
    }

    private function validateSchedule(): void
    {
        $this->formTime = $this->formTime === '' ? null : $this->formTime;
        $this->validate([
            'formTime' => ['nullable', 'date_format:H:i'],
            'formDuration' => ['nullable', 'integer', 'between:1,1440'],
        ]);
    }

    public function resetSchedule(): void
    {
        $this->formTime = null;
        $this->formDuration = null;
    }

    public function saveSchedule(): void
    {
        abort_unless(auth()->check() && $this->editingId, 403);
        $this->validateSchedule();
        MealPlanEntry::where('user_id', auth()->id())->findOrFail($this->editingId)
            ->update(['scheduled_time' => $this->formTime, 'duration_minutes' => $this->formDuration]);
        $this->scheduleMessage = 'Horario guardado.';
    }

    /** Fila vacía del compositor: Tipo · Elemento · Cantidad · Unidad. */
    public function addRow(): void
    {
        $this->formItems[] = $this->blankRow();
    }

    /** Ajusta los campos dependientes cuando cambia el tipo o el elemento de una fila. */
    public function updatedFormItems(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);
        if (! isset($this->formItems[$index])) {
            return;
        }
        $row = $this->formItems[$index];

        if ($field === 'kind') {
            $row = array_merge($this->blankRow(), ['key' => $row['key'], 'id' => $row['id'] ?? null, 'kind' => (string) $value]);
            if (in_array($value, ['recipe', 'preparation'], true)) {
                $row['portions'] = 1;
            }
        } elseif ($field === 'recipe_id') {
            $recipe = $value ? Recipe::find($value) : null;
            $row['name'] = $recipe?->name ?? '';
            $row['recipe_calories'] = $recipe?->nutrition['calories'] ?? null;
        } elseif ($field === 'preparation_id') {
            $prep = $value ? MealPreparation::where('user_id', auth()->id())->where('cancelled', false)->find($value) : null;
            $row['name'] = $prep?->name ?? '';
            $row['recipe_calories'] = $prep?->nutrition['calories'] ?? null;
        } elseif ($field === 'product_id') {
            $product = $value ? ShoppingItem::where('user_id', auth()->id())->find($value) : null;
            $row['name'] = $product?->name ?? '';
            $row['unit'] = $product?->base_unit ?? '';
        }

        $this->formItems[$index] = $row;
    }

    private function blankRow(): array
    {
        return ['key' => 'row-'.str()->uuid(), 'id' => null, 'kind' => '', 'recipe_id' => null, 'preparation_id' => null, 'product_id' => null,
            'quantity' => null, 'unit' => '', 'name' => '', 'portions' => null, 'calories' => null, 'recipe_calories' => null, 'ingredients' => []];
    }

    private function rowFromItem(MealPlanEntryItem $item): array
    {
        $ingredients = $item->ingredients ?? [];
        $kind = match (true) {
            (bool) $item->recipe_id => 'recipe',
            (bool) $item->preparation_id => 'preparation',
            count($ingredients) === 1 && ! $item->calories => 'ingredient',
            default => 'free',
        };
        $link = $kind === 'ingredient' ? $ingredients[0] : null;

        return array_merge($this->blankRow(), [
            'key' => 'item-'.$item->id,
            'id' => $item->id,
            'kind' => $kind,
            'recipe_id' => $item->recipe_id,
            'preparation_id' => $item->preparation_id,
            'product_id' => $link['shopping_item_id'] ?? null,
            'quantity' => $link['quantity'] ?? null,
            'unit' => $link['unit'] ?? '',
            'name' => $item->recipe?->name ?? $item->name ?? '',
            'portions' => in_array($kind, ['recipe', 'preparation'], true) ? (float) ($item->portions ?? 1) : null,
            'calories' => $kind === 'free' ? $item->calories : null,
            'recipe_calories' => $item->preparation_id ? ($item->preparation?->nutrition['calories'] ?? null) : ($item->recipe?->nutrition['calories'] ?? null),
            // Un elemento libre heredado con varios ingredientes los conserva tal cual.
            'ingredients' => $kind === 'free' ? $ingredients : [],
        ]);
    }

    public function removeItem(int $index): void
    {
        if (!array_key_exists($index, $this->formItems)) {
            return;
        }

        unset($this->formItems[$index]);
        $this->formItems = array_values($this->formItems);
    }

    public function moveItem(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (!isset($this->formItems[$index], $this->formItems[$target])) {
            return;
        }

        [$this->formItems[$index], $this->formItems[$target]] = [$this->formItems[$target], $this->formItems[$index]];
        $this->formItems = array_values($this->formItems);
    }

    public function useCalculatedCalories(): void
    {
        $this->formCalories = null;
    }

    public function calculatedFormCalories(): int
    {
        return (int) round(collect($this->formItems)->sum(function ($item) {
            if (in_array($item['kind'] ?? '', ['recipe', 'preparation'], true)) {
                return ((float) ($item['recipe_calories'] ?? 0)) * ((float) ($item['portions'] ?? 1));
            }

            return (int) ($item['calories'] ?? 0);
        }));
    }

    public function formHasIncompleteCalories(): bool
    {
        return collect($this->formItems)->contains(fn ($item) =>
            in_array($item['kind'] ?? '', ['recipe', 'preparation'], true) && ($item['recipe_id'] || $item['preparation_id']) && $item['recipe_calories'] === null
        );
    }

    public function save(): void
    {
        $this->validateSchedule();
        $this->validate([
            'formDate' => ['required', 'date'],
            'formMealType' => ['required', Rule::in(array_keys($this->mealTypes))],
            'formNotes' => ['nullable', 'string', 'max:5000'],
            'formCalories' => ['nullable', 'integer', 'min:0'],
            'formItems' => ['required', 'array', 'min:1'],
            'formItems.*.kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'formItems.*.recipe_id' => ['nullable', 'required_if:formItems.*.kind,recipe', 'uuid'],
            'formItems.*.preparation_id' => ['nullable', 'required_if:formItems.*.kind,preparation', 'uuid'],
            'formItems.*.product_id' => ['nullable', 'required_if:formItems.*.kind,ingredient', 'uuid'],
            'formItems.*.quantity' => ['nullable', 'required_if:formItems.*.kind,ingredient', 'numeric', 'gt:0'],
            'formItems.*.name' => ['nullable', 'required_if:formItems.*.kind,free', 'string', 'max:255'],
            'formItems.*.portions' => ['nullable', 'numeric', 'gt:0'],
            'formItems.*.calories' => ['nullable', 'integer', 'min:0'],
        ], [
            'formItems.*.kind.required' => 'Elige el tipo.',
            'formItems.*.recipe_id.required_if' => 'Elige la receta.',
            'formItems.*.preparation_id.required_if' => 'Elige la preparación.',
            'formItems.*.product_id.required_if' => 'Elige el ingrediente.',
            'formItems.*.quantity.required_if' => 'Indica la cantidad.',
            'formItems.*.name.required_if' => 'Escribe el nombre del elemento.',
            'formItems.required' => 'Agrega al menos una receta o un elemento libre.',
            'formItems.min' => 'Agrega al menos una receta o un elemento libre.',
        ]);

        $recipeIds = collect($this->formItems)->pluck('recipe_id')->filter();
        if ($recipeIds->duplicates()->isNotEmpty()) {
            $this->addError('formItems', 'Una receta no puede repetirse dentro de la misma comida. Ajusta sus porciones.');
            return;
        }

        $validRecipeIds = Recipe::whereIn('id', $recipeIds)->pluck('id');
        if ($validRecipeIds->count() !== $recipeIds->count()) {
            $this->addError('formItems', 'Una de las recetas seleccionadas ya no está disponible.');
            return;
        }

        app(MealInventory::class)->plan((int) auth()->id(), $this->planData(), $this->operationKey.':save');

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        app(MealInventory::class)->rearrange((int) auth()->id(), $id, 'delete', [], $this->operationKey.':delete');
        $this->closeForm();
    }

    private function planData(): array
    {
        return ['entry_id' => $this->editingId, 'date' => $this->formDate, 'meal_type' => $this->formMealType, 'mode' => 'replace',
            'scheduled_time' => $this->formTime, 'duration_minutes' => $this->formDuration,
            'notes' => trim($this->formNotes) ?: null, 'calories' => $this->formCalories,
            'items' => collect($this->formItems)->map(fn ($row) => $this->itemFromRow($row))->values()->all()];
    }

    /** Traduce una fila del compositor a la estructura de componentes que guarda MealInventory. */
    private function itemFromRow(array $row): array
    {
        $item = ['id' => $row['id'] ?? null, 'recipe_id' => null, 'preparation_id' => null, 'name' => $row['name'] ?? null, 'portions' => null, 'calories' => null, 'ingredients' => []];

        return match ($row['kind'] ?? 'free') {
            'recipe' => ['recipe_id' => $row['recipe_id'], 'portions' => $row['portions'] ?: 1] + $item,
            'preparation' => ['preparation_id' => $row['preparation_id'], 'portions' => $row['portions'] ?: 1] + $item,
            'ingredient' => ['ingredients' => [['shopping_item_id' => $row['product_id'], 'quantity' => $row['quantity'], 'unit' => $row['unit'] ?: null]]] + $item,
            default => ['calories' => $row['calories'] === '' ? null : $row['calories'], 'ingredients' => $row['ingredients'] ?? []] + $item,
        };
    }

    public function consume(string $mode = 'home'): void
    {
        $this->validate(['outsideCalories' => ['nullable', 'integer', 'min:0'], 'outsideNotes' => ['string', 'max:5000']]);
        DB::transaction(function () use ($mode) {
            $inventory = app(MealInventory::class);
            if ($mode === 'outside' && empty($this->formItems)) {
                $inventory->consumeAt((int) auth()->id(), ['date' => $this->formDate, 'meal_type' => $this->formMealType, 'mode' => 'outside', 'notes' => $this->outsideNotes, 'calories' => $this->outsideCalories], $this->operationKey.':outside');
            } else {
                $saved = $inventory->plan((int) auth()->id(), $this->planData(), $this->operationKey.':consume-plan');
                $inventory->consume((int) auth()->id(), $saved['meal_id'], $mode === 'outside' ? ['mode' => 'outside', 'notes' => $this->outsideNotes, 'calories' => $this->outsideCalories] : ['mode' => 'home'], $this->operationKey.':consume');
            }
        });
        $this->openForm($this->formDate, $this->formMealType);
    }

    public function revertConsumption(): void
    {
        app(MealInventory::class)->revert((int) auth()->id(), (int) $this->editingId, $this->operationKey.':revert');
        $this->openForm($this->formDate, $this->formMealType);
    }

    /** Abre el diálogo de mover, copiar o intercambiar desde el menú ⋮ de la tarjeta. */
    public function openAction(int $id, string $action): void
    {
        abort_unless(in_array($action, ['move', 'copy', 'swap'], true), 404);
        $entry = MealPlanEntry::where('user_id', auth()->id())->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $entry->id;
        $this->actionType = $action;
        $this->targetDate = $entry->date->toDateString();
        $this->targetMealType = $entry->meal_type;
        $this->operationKey = (string) str()->uuid();
        $this->actionOpen = true;
    }

    public function closeAction(): void
    {
        $this->actionOpen = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function runAction(): void
    {
        $this->validate([
            'targetDate' => ['required', 'date'],
            'targetMealType' => ['required', Rule::in(array_keys($this->mealTypes))],
        ]);

        $this->rearrange($this->actionType);
        $this->actionOpen = false;
    }

    public function deleteEntry(int $id): void
    {
        app(MealInventory::class)->rearrange((int) auth()->id(), $id, 'delete', [], (string) str()->uuid().':delete');
    }

    public function rearrange(string $action): void
    {
        app(MealInventory::class)->rearrange((int) auth()->id(), (int) $this->editingId, $action, ['date' => $this->targetDate, 'meal_type' => $this->targetMealType], $this->operationKey.':'.$action);
        $this->closeForm();
    }

    public function render()
    {
        $weekStart = Carbon::parse($this->selectedDate)->startOfWeek();
        $weekDates = collect(range(0, 6))->map(fn ($day) => $weekStart->copy()->addDays($day));
        $entries = MealPlanEntry::where('user_id', auth()->id())->with(['items.recipe', 'items.preparation'])
            ->whereDate('date', '>=', $weekStart->toDateString())->whereDate('date', '<=', $weekStart->copy()->endOfWeek()->toDateString())
            ->get();

        // Calorías totales por día, sumando todas las comidas planificadas.
        $dailyCalories = $entries->where('status', 'planned')->groupBy(fn ($entry) => $entry->date->format('Y-m-d'))
            ->map(fn ($dayEntries) => $dayEntries->sum('effective_calories'));
        $consumedCalories = $entries->where('status', 'consumed')->groupBy(fn ($entry) => $entry->date->format('Y-m-d'))
            ->map(fn ($dayEntries) => $dayEntries->sum(fn ($entry) => $entry->consumption['calories'] ?? 0));
        $plannedDays = $dailyCalories->filter()->count();
        $weekCalories = $dailyCalories->sum();

        $typeOrder = array_flip(array_keys($this->mealTypes));
        $todayEntries = $entries->filter(fn ($entry) => $entry->date->isToday())
            ->sortBy(fn ($entry) => $typeOrder[$entry->meal_type] ?? 99)->values();
        $showToday = $weekDates->contains(fn ($date) => $date->isToday());

        $entries = $entries->groupBy(fn ($entry) => $entry->date->format('Y-m-d').'|'.$entry->meal_type);

        $recipeOptions = $this->showForm
            ? Recipe::query()->orderByRaw('CASE WHEN meal_type = ? THEN 0 ELSE 1 END', [$this->formMealType])->orderByDesc('favorite')->orderBy('name')->get(['id', 'name', 'meal_type', 'favorite', 'nutrition'])
            : collect();

        return view('livewire.meal.meal-weekly', compact('weekDates', 'entries', 'weekStart', 'dailyCalories', 'consumedCalories', 'plannedDays', 'weekCalories', 'todayEntries', 'showToday', 'recipeOptions') + [
            'weekLabel' => $this->weekLabel($weekStart),
            'products' => $this->showForm ? ShoppingItem::where('user_id', auth()->id())->orderBy('name')->get(['id', 'name', 'base_unit']) : collect(),
            'preparations' => $this->showForm ? MealPreparation::where('user_id', auth()->id())->where('cancelled', false)->orderByDesc('cooked_at')->get()->filter(fn ($p) => $p->remaining() > 0) : collect(),
        ]);
    }

    private function weekLabel(Carbon $start): string
    {
        $end = $start->copy()->endOfWeek();

        return $start->month === $end->month
            ? 'Semana del '.$start->day.' al '.$end->translatedFormat('j \d\e F')
            : 'Semana del '.$start->translatedFormat('j \d\e F').' al '.$end->translatedFormat('j \d\e F');
    }
}
