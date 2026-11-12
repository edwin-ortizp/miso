<?php

namespace App\Livewire\Settings;

use App\Services\Experience\PersonalExperience;
use Livewire\Component;

class ModulePreferences extends Component
{
    public array $modules = [];

    public function mount(PersonalExperience $experience): void
    {
        $this->modules = $experience->state((int) auth()->id())['modules'] ?? array_keys($experience->catalog());
    }

    public function save(PersonalExperience $experience): void
    {
        $experience->saveModules((int) auth()->id(), $this->modules);
        $this->redirectRoute('settings.section', ['section' => 'modules']);
    }

    public function render()
    {
        return view('livewire.settings.module-preferences', ['catalog' => app(PersonalExperience::class)->catalog()]);
    }
}
