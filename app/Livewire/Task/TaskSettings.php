<?php

namespace App\Livewire\Task;

use App\Actions\ManageTaskCategories;
use App\Actions\ManageTaskStatuses;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\ModuleSetting;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Services\TaskCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ajustes de tareas con dos secciones (navegación lateral): categorías y estados.
 *
 * Catálogo personal de categorías de tareas. Renombrar no toca las tareas (guardan el key);
 * eliminar una categoría en uso pide a cuál pasar sus tareas o dejarlas sin categoría.
 *
 * Cada sección se presenta con la Card de gestión compartida: búsqueda, contador y
 * paginación sobre el catálogo completo, con el orden jerárquico (categorías) o el orden
 * por tipo (estados) preservado. La disponibilidad de Subir/Bajar se calcula sobre ese
 * catálogo completo, no sobre la página visible.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de tareas')]
class TaskSettings extends Component
{
    use WithManagementCard;

    public const ICONS = [
        'bi-tag' => 'Etiqueta', 'bi-person' => 'Persona', 'bi-briefcase' => 'Trabajo', 'bi-heart-pulse' => 'Salud',
        'bi-cash-coin' => 'Dinero', 'bi-mortarboard' => 'Estudio', 'bi-house' => 'Hogar', 'bi-people' => 'Social',
        'bi-palette' => 'Creatividad', 'bi-cpu' => 'Tecnología', 'bi-bag' => 'Compras', 'bi-file-earmark-text' => 'Documento',
        'bi-car-front' => 'Vehículo', 'bi-airplane' => 'Viaje', 'bi-star' => 'Estrella', 'bi-three-dots' => 'Otros',
    ];

    public const SECTIONS = ['categories', 'statuses'];

    public string $section = 'categories';

    #[Url(as: 'q', history: true, except: '')]
    public string $q = '';

    public bool $showForm = false;

    public bool $showStatusForm = false;

    public ?string $editingStatusId = null;

    public string $statusName = '';

    public string $statusType = TaskStatus::TODO;

    public ?string $deletingStatusId = null;

    public string $statusReassignTo = '';

    public ?string $editingId = null;

    public string $parent_id = '';

    public string $name = '';

    public string $codePrefix = '';

    public string $globalCodePrefix = 'MSO';

    public string $icon = 'bi-tag';

    public ?string $deletingId = null;

    public string $reassignTo = '';

    public string $message = '';

    public function mount(string $section = 'categories'): void
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $this->section = $section;
        $this->globalCodePrefix = app(TaskCodeService::class)->globalPrefix(auth()->id());
    }

    public function saveGlobalPrefix(): void
    {
        $this->resetValidation('globalCodePrefix');
        try {
            DB::transaction(function () {
                $codes = app(TaskCodeService::class);
                $codes->lock(auth()->id());
                $prefix = $codes->validatePrefix(auth()->id(), $this->globalCodePrefix, global: true);
                $setting = ModuleSetting::firstOrCreate(['module' => 'tasks']);
                $setting->update(['settings' => [...($setting->settings ?? []), 'code_prefix' => $prefix]]);
                $this->globalCodePrefix = $prefix;
                $this->message = 'Prefijo guardado. Los códigos existentes se conservan.';
            }, 3);
        } catch (InvalidArgumentException $e) {
            $this->addError('globalCodePrefix', $e->getMessage());
        }
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function openForm(?string $id = null): void
    {
        if ($this->section === 'statuses') {
            $this->openStatusForm($id);

            return;
        }

        $this->resetValidation();
        $this->parent_id = '';
        $this->message = '';
        $this->editingId = null;
        $this->name = '';
        $this->codePrefix = '';
        $this->icon = 'bi-tag';

        if ($id && ($category = TaskCategory::find($id))) {
            $this->editingId = $category->id;
            $this->name = $category->name;
            $this->codePrefix = $category->code_prefix ?? '';
            $this->parent_id = $category->parent_id ?? '';
            $this->icon = $category->icon ?: 'bi-tag';
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(ManageTaskCategories $categories): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['required', Rule::in(array_keys(self::ICONS))],
        ], [], ['name' => 'nombre', 'icon' => 'icono']);

        $category = $this->editingId ? TaskCategory::find($this->editingId) : null;

        try {
            if ($category) {
                $categories->update($category, $this->name, $this->icon, $this->parent_id ?: null, $this->codePrefix ?: null);
                $this->message = "“{$category->name}” actualizada. Sus tareas la conservan.";
            } else {
                $category = $categories->create(auth()->user(), $this->name, $this->icon, $this->parent_id ?: null, $this->codePrefix ?: null);
                $this->message = "“{$category->name}” ya está disponible al crear tareas.";
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'prefijo') ? 'codePrefix' : 'name', $exception->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function move(string $id, int $direction, ManageTaskCategories $categories): void
    {
        if ($category = TaskCategory::find($id)) {
            $categories->move($category, $direction);
        }
    }

    /** Sin tareas se elimina directamente (la confirmación la pide la vista); con tareas, se pregunta a dónde moverlas. */
    public function delete(string $id, ManageTaskCategories $categories): void
    {
        $category = TaskCategory::find($id);

        if (! $category) {
            return;
        }

        if ($category->children()->exists()) {
            $this->message = 'Mueve primero las subcategorías antes de eliminar esta categoría.';

            return;
        }
        if (Task::where('category', $category->key)->exists()) {
            $this->resetValidation();
            $this->deletingId = $category->id;
            $this->reassignTo = '';

            return;
        }

        $categories->delete($category);
        $this->message = "“{$category->name}” eliminada.";
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->reassignTo = '';
        $this->resetValidation();
    }

    public function reassignAndDelete(ManageTaskCategories $categories): void
    {
        $category = $this->deletingId ? TaskCategory::find($this->deletingId) : null;

        if (! $category) {
            $this->cancelDelete();

            return;
        }

        $this->validate([
            'reassignTo' => ['nullable', Rule::in(TaskCategory::whereKeyNot($category->id)->pluck('id')->all())],
        ]);

        $target = $this->reassignTo !== '' ? TaskCategory::find($this->reassignTo) : null;
        $moved = $categories->delete($category, $target);

        $this->message = "“{$category->name}” eliminada. {$moved} ".($moved === 1 ? 'tarea pasó' : 'tareas pasaron')
            .($target ? " a “{$target->name}”." : ' a Sin categoría.');
        $this->cancelDelete();
    }

    public function openStatusForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->message = '';
        $this->editingStatusId = null;
        $this->statusName = '';
        $this->statusType = TaskStatus::TODO;

        if ($id && ($status = TaskStatus::find($id))) {
            $this->editingStatusId = $status->id;
            $this->statusName = $status->name;
            $this->statusType = $status->type;
        }

        $this->showStatusForm = true;
    }

    public function closeStatusForm(): void
    {
        $this->showStatusForm = false;
        $this->editingStatusId = null;
        $this->resetValidation();
    }

    public function saveStatus(ManageTaskStatuses $statuses): void
    {
        $this->validate([
            'statusName' => ['required', 'string', 'max:60'],
            'statusType' => ['required', Rule::in(array_keys(TaskStatus::TYPES))],
        ], [], ['statusName' => 'nombre', 'statusType' => 'tipo']);

        $status = $this->editingStatusId ? TaskStatus::find($this->editingStatusId) : null;

        try {
            if ($status) {
                $statuses->update($status, $this->statusName, $this->statusType);
                $this->message = "Estado “{$status->name}” actualizado.";
            } else {
                $status = $statuses->create(auth()->user(), $this->statusName, $this->statusType);
                $this->message = "Estado “{$status->name}” creado como {$status->type_label}.";
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'tipo') || str_contains($exception->getMessage(), 'terminado') ? 'statusType' : 'statusName', $exception->getMessage());

            return;
        }

        $this->closeStatusForm();
    }

    public function moveStatus(string $id, int $direction, ManageTaskStatuses $statuses): void
    {
        if ($status = TaskStatus::find($id)) {
            $statuses->move($status, $direction);
        }
    }

    /** Sin tareas se elimina directamente (la vista confirma); con tareas, se pregunta a dónde pasarlas. */
    public function deleteStatus(string $id, ManageTaskStatuses $statuses): void
    {
        $status = TaskStatus::find($id);

        if (! $status) {
            return;
        }

        if ($status->tasks()->exists() && $status->type !== TaskStatus::DONE) {
            $this->resetValidation();
            $this->deletingStatusId = $status->id;
            $this->statusReassignTo = '';

            return;
        }

        try {
            $statuses->delete($status);
            $this->message = "Estado “{$status->name}” eliminado.";
        } catch (InvalidArgumentException $exception) {
            $this->message = $exception->getMessage();
        }
    }

    public function cancelStatusDelete(): void
    {
        $this->deletingStatusId = null;
        $this->statusReassignTo = '';
        $this->resetValidation();
    }

    public function reassignAndDeleteStatus(ManageTaskStatuses $statuses): void
    {
        $status = $this->deletingStatusId ? TaskStatus::find($this->deletingStatusId) : null;

        if (! $status) {
            $this->cancelStatusDelete();

            return;
        }

        $this->validate([
            'statusReassignTo' => ['required', Rule::in(TaskStatus::whereKeyNot($status->id)->where('type', '!=', TaskStatus::DONE)->pluck('id')->all())],
        ], [], ['statusReassignTo' => 'estado destino']);

        $target = TaskStatus::find($this->statusReassignTo);

        try {
            $moved = $statuses->delete($status, $target);
        } catch (InvalidArgumentException $exception) {
            $this->addError('statusReassignTo', $exception->getMessage());

            return;
        }

        $this->message = "Estado “{$status->name}” eliminado. {$moved} ".($moved === 1 ? 'tarea pasó' : 'tareas pasaron')." a “{$target->name}”.";
        $this->cancelStatusDelete();
    }

    public function render()
    {
        return view('livewire.task.task-settings', $this->section === 'statuses' ? $this->statusesData() : $this->categoriesData());
    }

    /**
     * @return array<string, mixed>
     */
    private function categoriesData(): array
    {
        $counts = Task::query()
            ->where('is_recurrence_history', false)
            ->selectRaw('category, SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as pending, COUNT(*) as total')
            ->groupBy('category')
            ->get()
            ->keyBy(fn ($row) => (string) $row->category);

        $full = $this->orderedCategories();
        $filtered = $this->filterByNameOrPath($full);

        [$canMoveUp, $canMoveDown] = $this->hierarchyMoveFlags($full);

        $deleting = $this->deletingId ? $full->firstWhere('id', $this->deletingId) : null;

        return [
            'paginator' => $this->paginateCollection($filtered),
            'catalogTotal' => $full->count(),
            'counts' => $counts,
            'uncategorized' => $counts[''] ?? null,
            'canMoveUp' => $canMoveUp,
            'canMoveDown' => $canMoveDown,
            'deleting' => $deleting,
            'deletingCount' => $deleting ? Task::where('category', $deleting->key)->count() : 0,
            'reassignOptions' => $deleting
                ? $full->reject(fn ($category) => $category->is($deleting))->pluck('name', 'id')->all()
                : [],
            'icons' => self::ICONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusesData(): array
    {
        $statusList = TaskStatus::ensureFor(auth()->id());
        $counts = Task::query()->where('is_recurrence_history', false)
            ->selectRaw('status_id, COUNT(*) as total')->groupBy('status_id')->pluck('total', 'status_id');
        $filtered = $this->filterByName($statusList);
        [$canMoveUp, $canMoveDown] = $this->typeMoveFlags($statusList);
        $deleting = $this->deletingStatusId ? $statusList->firstWhere('id', $this->deletingStatusId) : null;

        return [
            'paginator' => $this->paginateCollection($filtered),
            'catalogTotal' => $statusList->count(),
            'statusCounts' => $counts,
            'statusTypes' => collect(TaskStatus::TYPES)->mapWithKeys(fn ($definition, $type) => [$type => $definition['label']])->all(),
            'editableStatusTypes' => collect(TaskStatus::TYPES)->except(TaskStatus::DONE)->mapWithKeys(fn ($definition, $type) => [$type => $definition['label']])->all(),
            'canMoveUp' => $canMoveUp,
            'canMoveDown' => $canMoveDown,
            'todoStatusCount' => $statusList->where('type', TaskStatus::TODO)->count(),
            'editingStatus' => $this->editingStatusId ? $statusList->firstWhere('id', $this->editingStatusId) : null,
            'deletingStatus' => $deleting,
            'deletingStatusCount' => $deleting ? $deleting->tasks()->count() : 0,
            'statusReassignOptions' => $deleting
                ? $statusList->reject(fn ($status) => $status->is($deleting) || $status->type === TaskStatus::DONE)->pluck('name', 'id')->all()
                : [],
        ];
    }

    /**
     * Catálogo de categorías en su orden jerárquico completo (raíces y sus hijas,
     * cada nivel por sort_order y nombre), sin filtrar ni paginar.
     *
     * @return Collection<int, TaskCategory>
     */
    private function orderedCategories(): Collection
    {
        $order = array_flip(array_keys(TaskCategory::hierarchyOptions(auth()->id())));

        $categories = TaskCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (TaskCategory $category) => $order[$category->id] ?? PHP_INT_MAX)
            ->values();

        // La jerarquía es de dos niveles y el catálogo completo ya está cargado: la madre de
        // cada hija es una raíz de esta misma colección, así que se resuelve sin consultas extra
        // (la relación `parent` referencia $this->user_id y no se puede cargar con eager loading).
        $byId = $categories->keyBy('id');

        foreach ($categories as $category) {
            if ($category->parent_id) {
                $category->setRelation('parent', $byId->get($category->parent_id));
            }
        }

        return $categories;
    }

    /**
     * @param  Collection<int, TaskCategory>  $categories
     * @return Collection<int, TaskCategory>
     */
    private function filterByNameOrPath(Collection $categories): Collection
    {
        $search = trim($this->q);

        if ($search === '') {
            return $categories;
        }

        return $categories
            ->filter(fn (TaskCategory $category) => mb_stripos($category->name, $search) !== false
                || mb_stripos($category->path_label, $search) !== false)
            ->values();
    }

    /**
     * @param  Collection<int, TaskStatus>  $statuses
     * @return Collection<int, TaskStatus>
     */
    private function filterByName(Collection $statuses): Collection
    {
        $search = trim($this->q);

        if ($search === '') {
            return $statuses;
        }

        return $statuses->filter(fn (TaskStatus $status) => mb_stripos($status->name, $search) !== false)->values();
    }

    /**
     * Subir/Bajar disponibles según los vecinos reales dentro de cada rama (mismo padre),
     * igual conjunto ordenable que ManageTaskCategories::move.
     *
     * @param  Collection<int, TaskCategory>  $categories
     * @return array{0: array<string, bool>, 1: array<string, bool>}
     */
    private function hierarchyMoveFlags(Collection $categories): array
    {
        $up = [];
        $down = [];

        foreach ($categories->groupBy(fn (TaskCategory $category) => (string) ($category->parent_id ?? '')) as $siblings) {
            $siblings = $siblings->values();
            $last = $siblings->count() - 1;

            foreach ($siblings as $index => $sibling) {
                $up[$sibling->id] = $index > 0;
                $down[$sibling->id] = $index < $last;
            }
        }

        return [$up, $down];
    }

    /**
     * Subir/Bajar disponibles dentro del tipo, igual conjunto ordenable que ManageTaskStatuses::move.
     *
     * @param  Collection<int, TaskStatus>  $statuses
     * @return array{0: array<string, bool>, 1: array<string, bool>}
     */
    private function typeMoveFlags(Collection $statuses): array
    {
        $up = [];
        $down = [];

        foreach ($statuses->groupBy('type') as $siblings) {
            $siblings = $siblings->values();
            $last = $siblings->count() - 1;

            foreach ($siblings as $index => $sibling) {
                $up[$sibling->id] = $index > 0;
                $down[$sibling->id] = $index < $last;
            }
        }

        return [$up, $down];
    }
}
