<?php

namespace App\Livewire\Relationship;

use App\Livewire\Concerns\ManagesUserCategories;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ajustes de Relaciones: categorías de personas y de acontecimientos. */
#[Layout('layouts.app')]
#[Title('Ajustes de relaciones')]
class RelationshipSettings extends Component
{
    use ManagesUserCategories;

    public const SECTIONS = [
        'people' => ['label' => 'Categorías de personas', 'icon' => 'bi-people', 'route' => 'relationships.settings', 'domain' => 'contacts'],
        'events' => ['label' => 'Categorías de acontecimientos', 'icon' => 'bi-calendar-event', 'route' => 'relationships.settings.events', 'domain' => 'relationship-events'],
    ];

    #[Locked]
    public string $section = 'people';

    public function mount(string $section = 'people'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
        $this->useCategoryDomain(self::SECTIONS[$section]['domain']);
    }

    public function render()
    {
        return view('livewire.relationship.relationship-settings', ['sections' => self::SECTIONS] + $this->categoryViewData());
    }
}
