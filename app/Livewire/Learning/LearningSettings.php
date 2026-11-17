<?php

namespace App\Livewire\Learning;

use App\Livewire\Concerns\ManagesUserCategories;
use App\Services\Learning\LearningVocabulary;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** CHG-141: nombres de lo que se estudia según el tipo de formación. */
#[Layout('layouts.app')]
#[Title('Ajustes de Formación')]
class LearningSettings extends Component
{
    use ManagesUserCategories;

    public const SECTIONS = [
        'vocabulary' => ['label' => 'Vocabulario', 'icon' => 'bi-spellcheck', 'route' => 'learning.settings'],
        'categories' => ['label' => 'Categorías', 'icon' => 'bi-diagram-3', 'route' => 'learning.settings.categories'],
    ];

    #[Locked]
    public string $section = 'vocabulary';

    /** @var array<string, array<string, array{singular: string, plural: string}>> */
    public array $vocabulary = [];

    public ?string $message = null;

    public function mount(LearningVocabulary $vocabulary, string $section = 'vocabulary'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
        $this->useCategoryDomain('learning');
        $this->vocabulary = $vocabulary->all((int) auth()->id());
    }

    public function save(LearningVocabulary $vocabulary): void
    {
        $this->resetValidation();
        $vocabulary->save((int) auth()->id(), $this->vocabulary);
        $this->vocabulary = $vocabulary->all((int) auth()->id());
        $this->message = 'Vocabulario guardado.';
    }

    public function restore(LearningVocabulary $vocabulary): void
    {
        $this->resetValidation();
        $vocabulary->reset((int) auth()->id());
        $this->vocabulary = $vocabulary->all((int) auth()->id());
        $this->message = 'Se restauraron los nombres por defecto.';
    }

    public function render()
    {
        return view('livewire.learning.settings', ['vocab' => app(LearningVocabulary::class), 'uid' => (int) auth()->id(), 'sections' => self::SECTIONS]
            + ($this->section === 'categories' ? $this->categoryViewData() : []));
    }
}
