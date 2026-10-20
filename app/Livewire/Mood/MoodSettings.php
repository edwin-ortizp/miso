<?php

namespace App\Livewire\Mood;

use App\Models\MoodState;
use App\Support\DefaultMoodStates;
use App\Support\MoodCatalog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

/**
 * The personal emotion catalog. Every action here is recoverable: defaults are never
 * deleted, used emotions are deactivated instead, and Restaurar only adds or reactivates.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de emociones')]
class MoodSettings extends Component
{
    use WithManagementCard;
    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'cat', history: true)]
    public string $category = '';

    /** '' todas · 'active' · 'inactive' */
    #[Url(as: 'estado', history: true)]
    public string $status = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $emoji = '🙂';

    public string $text = '';

    public string $formCategory = 'Emocional';

    public int $value = 5;

    public bool $showRestoreConfirm = false;

    public string $message = '';

    public function openForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->message = '';
        $this->editingId = null;
        $this->emoji = '🙂';
        $this->text = '';
        $this->formCategory = 'Emocional';
        $this->value = 5;

        if ($id && ($state = MoodState::find($id))) {
            $this->editingId = $state->id;
            $this->emoji = $state->emoji;
            $this->text = $state->text;
            $this->formCategory = $state->category ?: 'Emocional';
            $this->value = $state->value;
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
        $state = $this->editingId ? MoodState::find($this->editingId) : null;

        if ($this->editingId && ! $state) {
            $this->missing();

            return;
        }

        $validated = $this->validate($this->formRules($state?->id));
        $payload = [
            'emoji' => $validated['emoji'],
            'text' => $validated['text'],
            'category' => $validated['formCategory'],
            'value' => $validated['value'],
        ];

        if ($state) {
            $this->catalog()->update($state, $payload);
            $this->message = 'Emoción actualizada. Tus registros anteriores conservan cómo se veían.';
        } else {
            $this->catalog()->create(auth()->user(), $payload);
            $this->message = 'Emoción creada y disponible en los selectores.';
        }

        $this->closeForm();
    }

    public function toggleActive(string $id): void
    {
        $state = MoodState::find($id);

        if (! $state) {
            $this->missing();

            return;
        }

        $wasActive = $state->is_active;
        $this->catalog()->setActive($state, ! $wasActive);

        $this->message = $wasActive
            ? "“{$state->text}” ya no aparecerá en nuevos registros; tu historial se conserva."
            : "“{$state->text}” vuelve a estar disponible.";
    }

    public function togglePin(string $id): void
    {
        $state = MoodState::find($id);

        if (! $state) {
            $this->missing();

            return;
        }

        $wasPinned = $state->is_pinned;
        $this->catalog()->togglePin($state);

        $this->message = $wasPinned
            ? "“{$state->text}” ya no está fijada."
            : "“{$state->text}” aparecerá primero en el selector rápido.";
    }

    public function move(string $id, int $direction): void
    {
        $state = MoodState::find($id);

        if (! $state) {
            $this->missing();

            return;
        }

        $this->catalog()->move($state, $direction);
    }

    public function delete(string $id): void
    {
        $state = MoodState::find($id);

        if (! $state) {
            $this->missing();

            return;
        }

        $this->message = $this->catalog()->delete($state)
            ? 'Emoción eliminada.'
            : "“{$state->text}” tiene registros o es predeterminada: desactívala para dejar de usarla.";
    }

    public function confirmRestore(): void
    {
        $this->showRestoreConfirm = true;
    }

    public function cancelRestore(): void
    {
        $this->showRestoreConfirm = false;
    }

    public function restoreDefaults(): void
    {
        $summary = $this->catalog()->restore(auth()->user());
        $this->showRestoreConfirm = false;

        $this->message = $summary['created'] === 0 && $summary['reactivated'] === 0
            ? 'Tu catálogo predeterminado ya estaba completo.'
            : sprintf(
                'Catálogo restaurado: %d creadas y %d reactivadas. Tus emociones personalizadas siguen intactas.',
                $summary['created'],
                $summary['reactivated']
            );
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['status', 'category'];
    }

    public function render()
    {
        $term = trim($this->search);

        $states = MoodState::query()
            ->when($term !== '', fn ($query) => $query->where('text', 'like', "%{$term}%"))
            ->when($this->category !== '', fn ($query) => $query->whereIn('category', \App\Support\CategoryCatalog::expand('mood', [$this->category])))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->prioritized()
            ->withCount('entries')
            ->paginate($this->perPage());

        return view('livewire.mood.mood-settings', [
            'states' => $states,
            'categories' => \App\Support\CategoryCatalog::options('mood'),
            'activeCount' => MoodState::active()->count(),
            'totalCount' => MoodState::count(),
            'pinnedCount' => MoodState::active()->where('is_pinned', true)->count(),
            'missingDefaults' => count(DefaultMoodStates::all()) - MoodState::whereNotNull('default_key')->count(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function formRules(?string $ignoreId): array
    {
        $rules = $this->catalog()->rules(auth()->user(), $ignoreId);

        return [
            'emoji' => $rules['emoji'],
            'text' => $rules['text'],
            'formCategory' => $rules['category'],
            'value' => $rules['value'],
        ];
    }

    /** An id that resolves to nothing is either gone or somebody else's: same answer. */
    private function missing(): void
    {
        $this->message = 'Esa emoción ya no está disponible.';
    }

    private function catalog(): MoodCatalog
    {
        return app(MoodCatalog::class);
    }

    protected function validationAttributes(): array
    {
        return [
            'emoji' => 'emoji',
            'text' => 'nombre',
            'formCategory' => 'categoría',
            'value' => 'valencia',
        ];
    }

    protected function messages(): array
    {
        return [
            'emoji.not_regex' => 'Usa un emoji, no letras ni números.',
            'text.unique' => 'Ya tienes una emoción con ese nombre.',
        ];
    }
}
