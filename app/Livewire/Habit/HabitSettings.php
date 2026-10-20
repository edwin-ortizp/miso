<?php

namespace App\Livewire\Habit;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\HabitAction;
use App\Models\HabitDefinition;
use App\Support\DefaultHabitDefinitions;
use App\Support\Habits\HabitActionField;
use App\Support\Habits\HabitActionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catálogo personal de hábitos. Además de crearlos y editarlos, aquí se decide
 * qué pasa al completarlos: nada, registrar en otro módulo con un valor fijo, o
 * preguntar primero. Lo que se ofrece sale de App\Support\Habits\HabitActionRegistry,
 * así que conectar un módulo nuevo no toca esta pantalla.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de hábitos')]
class HabitSettings extends Component
{
    use WithManagementCard;

    public const TIMES_OF_DAY = [
        'morning' => 'Mañana',
        'afternoon' => 'Tarde',
        'night' => 'Noche',
        'anytime' => 'En cualquier momento',
    ];

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'time', history: true, except: '')]
    public string $timeFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $icon = '';

    public string $timeOfDay = 'morning';

    public string $goalDuration = '';

    public string $baseTime = '';

    /** Clave de la acción elegida; vacío significa «solo marcarlo». */
    public string $actionKey = '';

    public string $actionMode = HabitAction::MODE_AUTO;

    /** @var array<string, mixed> */
    public array $actionConfig = [];

    public string $message = '';

    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->message = '';
        $this->editingId = null;
        $this->name = '';
        $this->icon = '';
        $this->timeOfDay = 'morning';
        $this->goalDuration = '';
        $this->baseTime = '';
        $this->actionKey = '';
        $this->actionMode = HabitAction::MODE_AUTO;
        $this->actionConfig = [];

        if ($id && ($habit = HabitDefinition::with('action')->find($id))) {
            $this->editingId = $habit->id;
            $this->name = $habit->name;
            $this->icon = $habit->icon ?? '';
            $this->timeOfDay = $habit->time_of_day ?: 'anytime';
            $this->goalDuration = $habit->goal_duration ?? '';
            $this->baseTime = $habit->base_time ?? '';

            if ($habit->action && $this->registry()->has($habit->action->action_key)) {
                $this->actionKey = $habit->action->action_key;
                $this->actionMode = $habit->action->mode;
                $this->actionConfig = array_merge(
                    $this->registry()->find($this->actionKey)->defaults(),
                    $habit->action->config ?? [],
                );
            }
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    /** Cambiar de módulo parte de cero: la configuración anterior no aplica al nuevo. */
    public function updatedActionKey(): void
    {
        $this->resetValidation();
        $this->actionConfig = $this->registry()->find($this->actionKey)?->defaults() ?? [];
    }

    public function save(): void
    {
        $habit = $this->editingId ? HabitDefinition::find($this->editingId) : null;

        if ($this->editingId && ! $habit) {
            $this->message = 'Ese hábito ya no está disponible.';
            $this->closeForm();

            return;
        }

        $validated = $this->validate($this->rules(), [], $this->validationAttributes());

        DB::transaction(function () use ($habit, $validated): void {
            $payload = [
                'name' => trim($validated['name']),
                'icon' => $validated['icon'] ?: null,
                'time_of_day' => $validated['timeOfDay'],
                'goal_duration' => $validated['goalDuration'] ?: null,
                'base_time' => $validated['baseTime'] ?: null,
            ];

            $habit = $habit ? tap($habit)->update($payload) : HabitDefinition::create($payload);

            $this->syncAction($habit, $validated);
        });

        $this->message = $this->editingId
            ? "“{$validated['name']}” actualizado."
            : "“{$validated['name']}” ya aparece en tu registro diario.";
        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $habit = HabitDefinition::find($id);

        if (! $habit) {
            $this->message = 'Ese hábito ya no está disponible.';

            return;
        }

        $name = $habit->name;
        $habit->delete();
        $this->message = "“{$name}” eliminado junto con su historial.";
    }

    public function restoreDefaults(): void
    {
        DefaultHabitDefinitions::createPositiveFor(auth()->user());
        $this->message = 'Los hábitos predeterminados que faltaban ya están en tu lista.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTimeFilter(): void
    {
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['timeFilter'];
    }

    public function render()
    {
        $term = trim($this->search);

        $habits = HabitDefinition::query()
            ->with('action')
            ->withCount(['habitCompletions as completions_count' => fn ($query) => $query->where('completed', true)])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.$term.'%'))
            ->when($this->timeFilter !== '', fn ($query) => $query->where('time_of_day', $this->timeFilter))
            // CASE en vez de FIELD(): los tests corren en sqlite y producción en MySQL.
            ->orderByRaw("CASE time_of_day WHEN 'morning' THEN 1 WHEN 'afternoon' THEN 2 WHEN 'night' THEN 3 ELSE 4 END")
            ->orderBy('base_time')
            ->orderBy('id')
            ->paginate($this->perPage());

        return view('livewire.habit.habit-settings', [
            'habits' => $habits,
            'totalCount' => HabitDefinition::count(),
            'timesOfDay' => self::TIMES_OF_DAY,
            'actionOptions' => $this->registry()->grouped(),
            'actionFields' => $this->currentFields(),
            'actionModes' => HabitAction::MODES,
            'registry' => $this->registry(),
        ]);
    }

    /** @return array<int, HabitActionField> */
    private function currentFields(): array
    {
        return $this->registry()->find($this->actionKey)?->fields() ?? [];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120', Rule::unique('habit_definitions', 'name')
                ->where('user_id', auth()->id())
                ->where('time_of_day', $this->timeOfDay)
                ->ignore($this->editingId)],
            'icon' => ['nullable', 'string', 'max:16'],
            'timeOfDay' => ['required', Rule::in(array_keys(self::TIMES_OF_DAY))],
            'goalDuration' => ['nullable', 'string', 'max:30'],
            'baseTime' => ['nullable', 'date_format:H:i'],
            'actionKey' => ['nullable', Rule::in($this->registry()->keys())],
            'actionMode' => ['required_with:actionKey', Rule::in(array_keys(HabitAction::MODES))],
        ];

        foreach ($this->currentFields() as $field) {
            // En modo «preguntar», los campos que se preguntan se llenan al completar.
            $rules['actionConfig.'.$field->key] = $this->actionMode === HabitAction::MODE_PROMPT && $field->ask
                ? ['nullable']
                : $field->rules;
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function validationAttributes(): array
    {
        $attributes = [
            'name' => 'nombre',
            'timeOfDay' => 'momento del día',
            'baseTime' => 'hora base',
            'actionKey' => 'módulo relacionado',
            'actionMode' => 'forma de registrar',
        ];

        foreach ($this->currentFields() as $field) {
            $attributes['actionConfig.'.$field->key] = mb_strtolower($field->label);
        }

        return $attributes;
    }

    /** @param array<string, mixed> $validated */
    private function syncAction(HabitDefinition $habit, array $validated): void
    {
        if (! $validated['actionKey']) {
            $habit->action()->delete();

            return;
        }

        $habit->action()->updateOrCreate([], [
            'action_key' => $validated['actionKey'],
            'mode' => $validated['actionMode'],
            'config' => array_filter(
                $validated['actionConfig'] ?? [],
                fn ($value) => $value !== null && $value !== '',
            ),
            'enabled' => true,
        ]);
    }

    private function registry(): HabitActionRegistry
    {
        return app(HabitActionRegistry::class);
    }
}
