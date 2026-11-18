<?php

namespace App\Livewire\Learning;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\LearningFormation;
use App\Models\LearningUnit;
use App\Services\Learning\LearningRecords;
use App\Support\CategoryCatalog;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Formación')]
class LearningPage extends Component
{
    use WithManagementCard;

    #[Locked]
    public ?string $formationId = null;

    #[Locked]
    public ?string $unitId = null;

    #[Locked]
    public ?string $editingId = null;

    #[Locked]
    public string $editor = 'formation';

    #[Locked]
    public ?string $editorParent = null;

    #[Locked]
    public string $editorKind = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'type', history: true)]
    public string $typeFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = '';

    public bool $showForm = false;

    public array $draft = [];

    public string $message = '';

    public string $formSection = 'basic';

    #[Url(as: 'section')]
    public string $subjectSection = '';

    #[Url]
    public string $evaluation = '';

    public function mount(?string $formation = null, ?string $unit = null): void
    {
        $this->formationId = $formation;
        $this->unitId = $unit;
        $this->context();
        $this->message = session('learning-feedback', '');
    }

    private function records(): LearningRecords
    {
        return app(LearningRecords::class);
    }

    private function context(): array
    {
        $formation = $this->formationId ? $this->records()->formation((int) auth()->id(), $this->formationId) : null;
        $unit = $this->unitId ? $this->records()->unit((int) auth()->id(), $formation->id, $this->unitId) : null;

        return [$formation, $unit];
    }

    protected function filterProperties(): array
    {
        return ['search', 'typeFilter', 'statusFilter', 'categoryFilter'];
    }

    public function updated($property): void
    {
        if (in_array($property, $this->filterProperties(), true)) {
            $this->resetPage();
        }
    }

    public function openFormation(?string $id = null, string $type = ''): void
    {
        $record = $id ? $this->records()->formation((int) auth()->id(), $id) : null;
        $this->resetEditor();
        $this->editor = 'formation';
        $this->editingId = $id;
        $this->draft = $record ? $this->draftFor($record) : ['type' => $type, 'title' => '', 'description' => '', 'status' => 'in_progress', 'category' => '', 'institution' => '', 'start_date' => '', 'end_date' => ''];
        $this->showForm = true;
    }

    public function openUnit(string $kind = '', ?string $id = null): void
    {
        [$formation, $unit] = $this->context();
        abort_unless($formation, 404);
        $record = $id ? $this->records()->unit((int) auth()->id(), $formation->id, $id) : null;
        $parentId = $record?->parent_id ?? $unit?->id;
        // Editing the current context must retain its own parent, including null.
        if ($record) {
            $parentId = $record->parent_id;
        }
        $parent = $parentId ? $this->records()->unit((int) auth()->id(), $formation->id, $parentId) : null;
        $kind = $record?->kind ?? $kind;
        abort_unless(in_array($kind, LearningRecords::children($parent?->kind ?? $formation->kind), true), 422);
        $this->resetEditor();
        $this->editor = 'unit';
        $this->editingId = $id;
        $this->editorParent = $parentId;
        $this->editorKind = $kind;
        $this->draft = $record ? $this->draftFor($record) : ['title' => '', 'description' => '', 'status' => 'pending', 'start_date' => '', 'end_date' => '', 'credits' => ''];
        if ($record && $kind === 'subject' && $formation->kind === 'academic') {
            $this->draft['semester'] = $parentId ?? '';
        }
        $this->showForm = true;
    }

    public function openSemester(string $id): void
    {
        $this->openUnit(id: $id);
        abort_unless(array_key_exists('semester', $this->draft), 422);
        $this->formSection = 'organization';
        $this->dispatch('learning-form-section', section: 'organization');
    }

    private function draftFor($record): array
    {
        $values = $record->only(['title', 'description', 'status', ...($record instanceof LearningFormation ? ['type', 'category', 'institution'] : ['credits'])]);
        foreach (['start_date', 'end_date'] as $key) {
            $values[$key] = $record->$key?->format('Y-m-d') ?? '';
        }

        return $values;
    }

    private function resetEditor(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'editorParent', 'editorKind', 'draft', 'formSection']);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetEditor();
    }

    public function save(): void
    {
        abort_unless($this->showForm, 422);
        $feedback = 'Formación guardada.';
        $refreshDetail = false;
        try {
            if ($this->editor === 'formation') {
                $this->records()->saveFormation((int) auth()->id(), $this->editingId, $this->draft);
            } else {
                $saved = $this->records()->saveUnit((int) auth()->id(), $this->formationId, $this->editingId, $this->editorParent, $this->editorKind, $this->draft);
                if ($saved->parent_id !== $this->editorParent) {
                    $refreshDetail = $saved->id === $this->unitId;
                    $feedback = $saved->parent_id
                        ? 'Nueva ubicación: '.$this->records()->unit((int) auth()->id(), $this->formationId, $saved->parent_id)->title.'.'
                        : 'Nueva ubicación: '.$this->records()->formation((int) auth()->id(), $this->formationId)->title.'.';
                }
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('draft.'.$field, $messages[0]);
            }
            $this->formSection = count(array_intersect(array_keys($e->errors()), ['title', 'description', 'type', 'kind', 'status'])) ? 'basic' : 'organization';
            $this->dispatch('learning-form-section', section: $this->formSection);

            return;
        }
        $this->closeForm();
        $this->message = $feedback;
        if ($refreshDetail) {
            session()->flash('learning-feedback', $feedback);
            $this->redirectRoute('learning.units.show', [$this->formationId, $this->unitId], navigate: true);
        }
    }

    public function deleteRecord(string $formation, ?string $unit = null): void
    {
        $this->resetValidation('deletion');
        try {
            $this->records()->delete((int) auth()->id(), $formation, $unit);
        } catch (ValidationException $e) {
            $this->addError('deletion', $e->errors()['deletion'][0]);

            return;
        }
        if (($unit && $unit === $this->unitId) || (! $unit && $formation === $this->formationId)) {
            $this->redirectRoute($unit ? 'learning.show' : 'learning', $unit ? [$formation] : [], navigate: true);
        }
        $this->message = 'Elemento eliminado.';
    }

    public function move(string $id, int $direction): void
    {
        $this->records()->move((int) auth()->id(), $this->formationId, $id, $direction);
    }

    public function setPin(string $type, string $id, bool $pinned): void
    {
        $this->records()->pin((int) auth()->id(), $type, $id, $pinned);
        $this->message = $pinned ? 'Acceso directo fijado.' : 'Acceso directo retirado.';
    }

    #[\Livewire\Attributes\On('study-academic-changed')]
    public function refreshAcademicActions(): void {}

    public function render()
    {
        [$formation, $unit] = $this->context();
        $userId = (int) auth()->id();
        $allUnits = $formation ? LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formation->id)->orderBy('position')->orderBy('id')->get() : collect();
        $path = [];
        $ancestor = $unit;
        $seen = [];
        while ($ancestor && ! isset($seen[$ancestor->id])) {
            $seen[$ancestor->id] = true;
            array_unshift($path, $ancestor);
            $ancestor = $allUnits->firstWhere('id', $ancestor->parent_id);
        }
        $categories = CategoryCatalog::options('learning', $userId);
        if ($formation) {
            $records = $allUnits->where('parent_id', $unit?->id)->values();
            $paginator = null;
        } else {
            $query = LearningFormation::withoutGlobalScopes()->where('user_id', $userId)
                ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->categoryFilter !== '', fn ($q) => $q->whereIn('category', CategoryCatalog::expand('learning', [$this->categoryFilter], $userId)));
            $records = $paginator = $query->orderByDesc('created_at')->orderBy('id')->paginate($this->perPage());
        }
        $shortcuts = $this->records()->shortcuts($userId);
        $pinKeys = array_map(fn ($pin) => $pin['type'].':'.$pin['id'], $shortcuts);
        $childKinds = $formation ? LearningRecords::children($unit?->kind ?? $formation->kind) : [];
        $back = $formation ? ['href' => $unit ? ($unit->parent_id ? route('learning.units.show', [$formation->id, $unit->parent_id]) : route('learning.show', $formation->id)) : route('learning'), 'label' => 'Volver a '.($unit ? ($allUnits->firstWhere('id', $unit->parent_id)?->title ?? $formation->title) : 'Formación')] : null;
        $typeLocked = $this->editor === 'formation' && $this->editingId && LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $this->editingId)->exists();

        $editorParentLabel = $this->editorParent ? $allUnits->firstWhere('id', $this->editorParent)?->title : null;

        $semesters = $allUnits->where('kind', 'period')->pluck('title', 'id')->all();

        return view('livewire.learning.page', compact('semesters', 'formation', 'unit', 'records', 'paginator', 'categories', 'path', 'shortcuts', 'pinKeys', 'childKinds', 'back', 'typeLocked', 'editorParentLabel'));
    }
}
