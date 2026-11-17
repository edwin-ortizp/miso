<?php

namespace App\Livewire\NegativeHabit;

use App\Livewire\Concerns\ManagesUserCategories;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ajustes de Hábitos a evitar: por ahora, las categorías con las que se agrupan. */
#[Layout('layouts.app')]
#[Title('Ajustes de hábitos a evitar')]
class NegativeHabitSettings extends Component
{
    use ManagesUserCategories;

    public const SECTIONS = [
        'categories' => ['label' => 'Categorías', 'icon' => 'bi-diagram-3', 'route' => 'negative-habits.settings'],
    ];

    #[Locked]
    public string $section = 'categories';

    public function mount(string $section = 'categories'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
        $this->useCategoryDomain('negative-habits');
    }

    public function render()
    {
        return view('livewire.negative-habit.negative-habit-settings', ['sections' => self::SECTIONS] + $this->categoryViewData());
    }
}
