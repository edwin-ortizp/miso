<?php

namespace App\Livewire\Exercise;

use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Support\ExerciseProgress;
use App\Support\DefaultExerciseTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

/**
 * Catálogo personal de tipos de ejercicio. Un tipo con registros no se borra a ciegas:
 * primero se reasignan sus registros a otro tipo, así el historial no se pierde.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de ejercicio')]
class ExerciseSettings extends Component
{
    use WithManagementCard;
    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'cat', history: true)]
    public string $category = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $icon = '🏃';

    public string $formCategory = 'cardio';

    public ?int $caloriesPerHour = null;

    public ?int $stepsEquivalent = 0;

    public ?string $deletingId = null;

    public string $reassignTo = '';

    public string $message = '';

    public ?int $dailyExerciseMinutes = null;

    public function mount(): void
    {
        $this->dailyExerciseMinutes = auth()->user()->daily_exercise_minutes ?: ExerciseProgress::DEFAULT_DAILY_MINUTES;
    }

    public function saveGoal(): void
    {
        $data = $this->validate(
            ['dailyExerciseMinutes' => ['required', 'integer', 'between:5,600']],
            [],
            ['dailyExerciseMinutes' => 'minutos activos'],
        );

        auth()->user()->update(['daily_exercise_minutes' => $data['dailyExerciseMinutes']]);
        $this->message = 'Meta diaria actualizada.';
    }

    public function openForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->message = '';
        $this->editingId = null;
        $this->name = '';
        $this->icon = '🏃';
        $this->formCategory = 'cardio';
        $this->caloriesPerHour = null;
        $this->stepsEquivalent = 0;

        if ($id && ($type = ExerciseType::find($id))) {
            $this->editingId = $type->id;
            $this->name = $type->name;
            $this->icon = $type->icon ?? '';
            $this->formCategory = $type->category ?? '';
            $this->caloriesPerHour = $type->calories_per_hour;
            $this->stepsEquivalent = $type->steps_equivalent;
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $type = $this->editingId ? ExerciseType::find($this->editingId) : null;

        if ($this->editingId && ! $type) {
            $this->message = 'Ese tipo de ejercicio ya no está disponible.';
            $this->closeForm();

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('exercise_types', 'name')
                ->where('user_id', auth()->id())
                ->ignore($type?->id)],
            'icon' => ['nullable', 'string', 'max:16'],
            'formCategory' => ['nullable', Rule::in(array_keys(\App\Support\CategoryCatalog::options('exercise')))],
            'caloriesPerHour' => ['required', 'integer', 'min:0', 'max:3000'],
            'stepsEquivalent' => ['nullable', 'integer', 'min:0', 'max:50000'],
        ]);

        $payload = [
            'name' => trim($validated['name']),
            'icon' => $validated['icon'] ?: null,
            'category' => $validated['formCategory'] ?: null,
            'calories_per_hour' => $validated['caloriesPerHour'],
            'steps_equivalent' => $validated['stepsEquivalent'] ?? 0,
        ];

        if ($type) {
            $type->update($payload);
            $this->message = "“{$type->name}” actualizado. Los registros anteriores conservan sus calorías y pasos.";
        } else {
            ExerciseType::create($payload);
            $this->message = "“{$payload['name']}” ya está disponible al registrar ejercicio.";
        }

        $this->closeForm();
    }

    /** Tipos sin registros: se eliminan directamente (la confirmación la pide la vista). */
    public function delete(string $id): void
    {
        $type = ExerciseType::withCount('logs')->find($id);

        if (! $type) {
            $this->message = 'Ese tipo de ejercicio ya no está disponible.';

            return;
        }

        if ($type->logs_count > 0) {
            $this->confirmDelete($id);

            return;
        }

        $type->delete();
        $this->message = "“{$type->name}” eliminado.";
    }

    /** Tipos con registros: se pide a qué tipo moverlos antes de eliminar. */
    public function confirmDelete(string $id): void
    {
        $this->resetValidation();
        $this->deletingId = $id;
        $this->reassignTo = '';
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->reassignTo = '';
        $this->resetValidation();
    }

    public function reassignAndDelete(): void
    {
        $type = $this->deletingId ? ExerciseType::find($this->deletingId) : null;

        if (! $type) {
            $this->cancelDelete();

            return;
        }

        $this->validate(
            ['reassignTo' => ['required', Rule::exists('exercise_types', 'id')->where('user_id', auth()->id()), Rule::notIn([$type->id])]],
            ['reassignTo.required' => 'Elige a qué tipo pasar los registros.'],
        );

        $target = ExerciseType::findOrFail($this->reassignTo);

        $moved = DB::transaction(function () use ($type, $target) {
            $moved = ExerciseLog::where('exercise_type_id', $type->id)->update(['exercise_type_id' => $target->id]);
            $type->delete();

            return $moved;
        });

        $this->message = "“{$type->name}” eliminado. {$moved} ".($moved === 1 ? 'registro pasó' : 'registros pasaron')." a “{$target->name}”.";
        $this->cancelDelete();
    }

    public function restoreDefaults(): void
    {
        $created = DefaultExerciseTypes::createFor(auth()->user());

        $this->message = $created === 0
            ? 'Tu catálogo ya tenía todos los tipos predeterminados.'
            : "Se agregaron {$created} tipos predeterminados. Tus tipos y registros siguen intactos.";
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['category'];
    }

    public function render()
    {
        $term = trim($this->search);

        $types = ExerciseType::query()
            ->withCount('logs')
            ->withMax('logs', 'date')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->when($this->category === 'none', fn ($query) => $query->whereNull('category'))
            ->when(! in_array($this->category, ['', 'none'], true), fn ($query) => $query->whereIn('category', \App\Support\CategoryCatalog::expand('exercise', [$this->category])))
            ->orderBy('name')
            ->paginate($this->perPage());

        $order = array_flip(array_keys(\App\Support\CategoryCatalog::options('exercise')));
        $groups = $types->getCollection()->groupBy(fn (ExerciseType $type) => $type->category ?? '')
            ->sortBy(fn ($group, $key) => $order[$key] ?? PHP_INT_MAX);

        $deleting = $this->deletingId ? ExerciseType::withCount('logs')->find($this->deletingId) : null;

        return view('livewire.exercise.exercise-settings', [
            'groups' => $groups,
            'types' => $types,
            'categories' => \App\Support\CategoryCatalog::options('exercise'),
            'totalCount' => ExerciseType::count(),
            'usedCount' => ExerciseType::has('logs')->count(),
            'deleting' => $deleting,
            'reassignOptions' => $deleting
                ? ExerciseType::whereKeyNot($deleting->id)->orderBy('name')->get()
                    ->mapWithKeys(fn ($type) => [$type->id => trim(($type->icon ?? '').' '.$type->name)])->all()
                : [],
        ]);
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'icon' => 'ícono',
            'formCategory' => 'categoría',
            'caloriesPerHour' => 'calorías por hora',
            'stepsEquivalent' => 'pasos por hora',
            'reassignTo' => 'tipo de destino',
        ];
    }

    protected function messages(): array
    {
        return ['name.unique' => 'Ya tienes un tipo de ejercicio con ese nombre.'];
    }
}
