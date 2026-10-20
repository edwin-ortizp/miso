<?php

namespace App\Livewire\Relationship;

use App\Models\Circle;
use App\Models\Relationship;
use App\Models\RelationshipContactMethod;
use App\Models\RelationshipTag;
use App\Support\Birthday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Relaciones')]
class RelationshipIndex extends Component
{
    use WithManagementCard;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'circle', history: true, keep: true)]
    public string $circleFilter = '';

    #[Url(as: 'tag', history: true, keep: true)]
    public string $tagFilter = '';

    #[Url(as: 'archived', history: true, keep: true)]
    public bool $showArchived = false;

    public const SORTS = [
        'name' => 'Nombre (A–Z)',
        'last' => 'Último contacto',
        'next' => 'Próximo contacto',
    ];

    #[Url(as: 'sort', history: true, except: 'name')]
    public string $sort = 'name';

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'name';
        $this->resetPage();
    }

    // Person form
    public bool $showForm = false;

    public ?string $editingId = null;

    public string $fullName = '';

    public string $nickname = '';

    public string $pronouns = '';

    public string $occupation = '';

    public string $organization = '';

    public string $address = '';

    public string $city = '';

    public string $documentType = '';

    public string $documentNumber = '';

    public string $generalNotes = '';

    public string $circleId = '';

    public string $category = 'otro';

    public ?int $birthdayMonth = null;

    public ?int $birthdayDay = null;

    public ?int $birthdayYear = null;

    public ?int $contactFrequencyDays = null;

    public array $selectedTags = [];

    public array $contactMethods = [];

    // Circle form
    public bool $showCircleForm = false;

    public ?string $editingCircleId = null;

    public string $circleName = '';

    public ?int $circleFrequencyDays = 30;

    // Tag form
    public bool $showTagForm = false;

    public ?string $editingTagId = null;

    public string $tagName = '';

    public array $categories = [
        'familia' => 'Familia',
        'amigo' => 'Amigo',
        'trabajo' => 'Trabajo',
        'pareja' => 'Pareja',
        'otro' => 'Otro',
    ];

    public function mount(): void
    {
        $this->categories = \App\Support\CategoryCatalog::options('contacts');
        $this->normalizeFilters();

        // «Editar contacto» desde el detalle abre aquí el formulario de la persona.
        $editing = request()->query('edit');

        if (is_string($editing) && Relationship::query()->whereKey($editing)->exists()) {
            $this->openForm($editing);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCircleFilter(): void
    {
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function updatedTagFilter(): void
    {
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function updatedShowArchived(): void
    {
        $this->resetPage();
    }

    public function openForm(?string $id = null): void
    {
        $this->resetForm();

        if ($id) {
            $relationship = Relationship::with(['contactMethods', 'tags'])->findOrFail($id);

            $this->editingId = $relationship->id;
            $this->fullName = $relationship->full_name;
            $this->nickname = $relationship->nickname ?? '';
            $this->pronouns = $relationship->pronouns ?? '';
            $this->occupation = $relationship->occupation ?? '';
            $this->organization = $relationship->organization ?? '';
            $this->address = $relationship->address ?? '';
            $this->city = $relationship->city ?? '';
            $this->documentType = $relationship->document_type ?? '';
            $this->documentNumber = $relationship->document_number ?? '';
            $this->generalNotes = $relationship->general_notes ?? '';
            $this->circleId = $relationship->circle_id ?? '';
            $this->category = $relationship->category ?: 'otro';
            $this->birthdayMonth = $relationship->birthday_month;
            $this->birthdayDay = $relationship->birthday_day;
            $this->birthdayYear = $relationship->birthday_year;
            $this->contactFrequencyDays = $relationship->contact_frequency_days;
            $this->selectedTags = $relationship->tags->pluck('id')->all();
            $this->contactMethods = $relationship->contactMethods
                ->map(fn (RelationshipContactMethod $method) => [
                    'id' => $method->id,
                    'type' => $method->type,
                    'label' => $method->label ?? '',
                    'value' => $method->value,
                    'is_primary' => $method->is_primary,
                ])->all();
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function addContactMethod(): void
    {
        $this->contactMethods[] = ['id' => null, 'type' => 'phone', 'label' => '', 'value' => '', 'is_primary' => false];
    }

    public function removeContactMethod(int $index): void
    {
        unset($this->contactMethods[$index]);
        $this->contactMethods = array_values($this->contactMethods);
    }

    public function save(): void
    {
        $validated = $this->validate($this->formRules(), $this->formMessages());

        if ($validated['birthdayMonth'] && $validated['birthdayDay']
            && ! Birthday::isValidCombination((int) $validated['birthdayMonth'], (int) $validated['birthdayDay'])) {
            $this->addError('birthdayDay', 'La combinación de día y mes no existe.');

            return;
        }

        $attributes = [
            'full_name' => trim($validated['fullName']),
            'nickname' => trim($validated['nickname']) ?: null,
            'pronouns' => trim($validated['pronouns']) ?: null,
            'occupation' => trim($validated['occupation']) ?: null,
            'organization' => trim($validated['organization']) ?: null,
            'address' => trim($validated['address']) ?: null,
            'city' => trim($validated['city']) ?: null,
            'document_type' => trim($validated['documentNumber']) !== '' ? $validated['documentType'] : null,
            'document_number' => trim($validated['documentNumber']) ?: null,
            'general_notes' => trim($validated['generalNotes']) ?: null,
            'circle_id' => $validated['circleId'] ?: null,
            'category' => $validated['category'] ?: 'otro',
            'birthday_month' => $validated['birthdayMonth'] ?: null,
            'birthday_day' => $validated['birthdayDay'] ?: null,
            'birthday_year' => $validated['birthdayYear'] ?: null,
            'contact_frequency_days' => $validated['contactFrequencyDays'] ?: null,
        ];

        DB::transaction(function () use ($attributes): void {
            $relationship = $this->editingId
                ? tap(Relationship::findOrFail($this->editingId))->update($attributes)
                : Relationship::create($attributes);

            $relationship->syncTags($this->selectedTags);
            $this->persistContactMethods($relationship);
        });

        $this->closeForm();
    }

    public function toggleArchive(string $id): void
    {
        $relationship = Relationship::findOrFail($id);

        $relationship->update([
            'is_archived' => ! $relationship->is_archived,
            'archived_at' => $relationship->is_archived ? null : now(),
        ]);
    }

    public function markContact(string $id): void
    {
        Relationship::findOrFail($id)->update(['last_contact_at' => now()]);
    }

    public function delete(string $id): void
    {
        Relationship::findOrFail($id)->delete();
    }

    public function openCircleForm(?string $id = null): void
    {
        $this->editingCircleId = null;
        $this->circleName = '';
        $this->circleFrequencyDays = 30;

        if ($id) {
            $circle = Circle::findOrFail($id);
            $this->editingCircleId = $circle->id;
            $this->circleName = $circle->name;
            $this->circleFrequencyDays = $circle->contact_frequency_days;
        }

        $this->resetValidation(['circleName', 'circleFrequencyDays']);
        $this->showCircleForm = true;
    }

    public function saveCircle(): void
    {
        $validated = $this->validate([
            'circleName' => ['required', 'string', 'max:120'],
            'circleFrequencyDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $attributes = [
            'name' => trim($validated['circleName']),
            'contact_frequency_days' => $validated['circleFrequencyDays'] ?: null,
        ];

        if ($this->editingCircleId) {
            Circle::findOrFail($this->editingCircleId)->update($attributes);
        } else {
            Circle::create($attributes);
        }

        $this->showCircleForm = false;
    }

    public function deleteCircle(string $id): void
    {
        $circle = Circle::findOrFail($id);

        Relationship::where('circle_id', $circle->id)->update(['circle_id' => null]);
        $circle->delete();

        if ($this->circleFilter === $id) {
            $this->circleFilter = '';
        }
    }

    public function openTagForm(?string $id = null): void
    {
        $this->editingTagId = null;
        $this->tagName = '';

        if ($id) {
            $tag = RelationshipTag::findOrFail($id);
            $this->editingTagId = $tag->id;
            $this->tagName = $tag->name;
        }

        $this->resetValidation(['tagName']);
        $this->showTagForm = true;
    }

    public function saveTag(): void
    {
        $validated = $this->validate([
            'tagName' => ['required', 'string', 'max:60'],
        ]);

        if ($this->editingTagId) {
            RelationshipTag::findOrFail($this->editingTagId)->update(['name' => trim($validated['tagName'])]);
        } else {
            RelationshipTag::create(['name' => trim($validated['tagName'])]);
        }

        $this->showTagForm = false;
    }

    public function deleteTag(string $id): void
    {
        RelationshipTag::findOrFail($id)->delete();

        if ($this->tagFilter === $id) {
            $this->tagFilter = '';
        }
    }

    protected function filterProperties(): array
    {
        return ['showArchived', 'circleFilter', 'tagFilter'];
    }

    public function render()
    {
        $circles = Circle::orderBy('sort_order')->orderBy('name')->get();
        $tags = RelationshipTag::orderBy('sort_order')->orderBy('name')->get();

        $relationships = $this->filteredQuery()
            ->with(['circle', 'tags', 'contactMethods'])
            // Las personas sin fecha de contacto van al final.
            ->when($this->sort === 'last', fn ($q) => $q->orderByRaw('CASE WHEN last_contact_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('last_contact_at'))
            ->when($this->sort === 'next', fn ($q) => $q->orderByRaw('CASE WHEN next_contact_suggested_at IS NULL THEN 1 ELSE 0 END')->orderBy('next_contact_suggested_at'))
            ->orderBy('full_name')
            ->paginate($this->perPage());

        return view('livewire.relationship.relationship-index', [
            'circles' => $circles,
            'tags' => $tags,
            'relationships' => $relationships,
            'sorts' => self::SORTS,
            'activeCount' => Relationship::active()->count(),
            'archivedCount' => Relationship::archived()->count(),
            'dueFollowUps' => $this->dueFollowUps(),
            'nextBirthdays' => $this->nextBirthdays(),
        ]);
    }

    private function filteredQuery(): Builder
    {
        $query = $this->showArchived ? Relationship::archived() : Relationship::active();

        if ($this->circleFilter !== '') {
            $query->where('circle_id', $this->circleFilter);
        }

        if ($this->tagFilter !== '') {
            $query->whereHas('tags', fn (Builder $tag) => $tag->whereKey($this->tagFilter));
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $normalized = RelationshipContactMethod::normalize($term);

            $query->where(function (Builder $inner) use ($term, $normalized): void {
                $inner->where('full_name', 'like', "%{$term}%")
                    ->orWhere('nickname', 'like', "%{$term}%")
                    ->orWhereHas(
                        'contactMethods',
                        fn (Builder $method) => $method
                            ->where('value', 'like', "%{$term}%")
                            ->orWhere('value_normalized', 'like', "%{$normalized}%")
                    );
            });
        }

        return $query;
    }

    /** @return \Illuminate\Support\Collection<int, Relationship> */
    private function dueFollowUps()
    {
        return Relationship::active()
            ->with('circle')
            ->whereNotNull('last_contact_at')
            ->get()
            ->filter(fn (Relationship $relationship) => $relationship->isFollowUpDue())
            ->sortBy(fn (Relationship $relationship) => $relationship->followUpDueAt())
            ->take(5)
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, array{relationship: Relationship, birthday: Birthday}> */
    private function nextBirthdays()
    {
        return Relationship::active()
            ->withBirthday()
            ->get()
            ->map(fn (Relationship $relationship) => [
                'relationship' => $relationship,
                'birthday' => $relationship->birthday(),
            ])
            ->filter(fn (array $row) => $row['birthday'] !== null)
            ->sortBy(fn (array $row) => $row['birthday']->daysUntil())
            ->take(3)
            ->values();
    }

    private function persistContactMethods(Relationship $relationship): void
    {
        $keptIds = [];

        foreach (array_values($this->contactMethods) as $position => $method) {
            if (trim((string) ($method['value'] ?? '')) === '') {
                continue;
            }

            $attributes = [
                'type' => $method['type'] ?? 'other',
                'label' => trim((string) ($method['label'] ?? '')) ?: null,
                'value' => trim((string) $method['value']),
                'is_primary' => (bool) ($method['is_primary'] ?? false),
                'sort_order' => $position,
            ];

            $existing = $method['id']
                ? $relationship->contactMethods()->whereKey($method['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $relationship->contactMethods()->create($attributes)->id;
        }

        $relationship->contactMethods()->whereKeyNot($keptIds)->delete();
    }

    private function formRules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:120'],
            'pronouns' => ['nullable', 'string', 'max:60'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'documentType' => ['nullable', 'required_with:documentNumber', 'in:'.implode(',', array_keys(Relationship::DOCUMENT_TYPES))],
            'documentNumber' => ['nullable', 'string', 'max:40'],
            'generalNotes' => ['nullable', 'string', 'max:5000'],
            'circleId' => ['nullable', 'string'],
            // The legacy free-form category predates circles and tags, so unknown values still load.
            'category' => ['required', 'string', 'max:60'],
            'birthdayMonth' => ['nullable', 'integer', 'between:1,12', 'required_with:birthdayDay'],
            'birthdayDay' => ['nullable', 'integer', 'between:1,31', 'required_with:birthdayMonth'],
            'birthdayYear' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'contactFrequencyDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'selectedTags' => ['array'],
            'contactMethods' => ['array'],
            'contactMethods.*.type' => ['required', 'in:'.implode(',', array_keys(RelationshipContactMethod::TYPES))],
            'contactMethods.*.label' => ['nullable', 'string', 'max:60'],
            'contactMethods.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function formMessages(): array
    {
        return [
            'birthdayMonth.required_with' => 'Indica también el mes de cumpleaños.',
            'documentType.required_with' => 'Indica el tipo de documento.',
            'birthdayDay.required_with' => 'Indica también el día de cumpleaños.',
        ];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->fullName = '';
        $this->nickname = '';
        $this->pronouns = '';
        $this->occupation = '';
        $this->organization = '';
        $this->address = '';
        $this->city = '';
        $this->documentType = '';
        $this->documentNumber = '';
        $this->generalNotes = '';
        $this->circleId = '';
        $this->category = 'otro';
        $this->birthdayMonth = null;
        $this->birthdayDay = null;
        $this->birthdayYear = null;
        $this->contactFrequencyDays = null;
        $this->selectedTags = [];
        $this->contactMethods = [];
        $this->resetValidation();
    }

    private function normalizeFilters(): void
    {
        if ($this->circleFilter !== '' && ! Circle::whereKey($this->circleFilter)->exists()) {
            $this->circleFilter = '';
        }

        if ($this->tagFilter !== '' && ! RelationshipTag::whereKey($this->tagFilter)->exists()) {
            $this->tagFilter = '';
        }
    }
}
