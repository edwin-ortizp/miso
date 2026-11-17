<?php

namespace App\Livewire\Settings;

use App\Services\Experience\PersonalExperience;
use Livewire\Component;

class ModulePreferences extends Component
{
    public array $modules = [];

    /** @var list<string> Funciones apagadas (`módulo.función`); CHG-139. */
    public array $featuresOff = [];

    /** Función cuyo apagado espera confirmación porque arrastra a otras. */
    public ?string $pendingOff = null;

    /** @var list<string> */
    public array $pendingDependents = [];

    public ?string $notice = null;

    public function mount(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $this->modules = $experience->state($userId)['modules'] ?? array_keys($experience->catalog());
        $this->featuresOff = $experience->featuresOff($userId);
    }

    /** Cambia una función: apagar con dependientes pide confirmación; encender reactiva lo que necesita. */
    public function toggleFeature(string $key, PersonalExperience $experience): void
    {
        abort_unless(array_key_exists($key, $experience->featureCatalog()), 422);
        $this->notice = null;
        $this->pendingOff = null;
        $this->pendingDependents = [];

        if (in_array($key, $this->featuresOff, true)) {
            $before = $this->featuresOff;
            $this->featuresOff = $experience->cascadeOn($key, $before);
            $extra = array_values(array_diff($before, $this->featuresOff, [$key]));
            if ($extra) {
                $this->notice = 'Al encender «'.$this->featureLabel($key, $experience).'» también se encendió '.$this->labelList($extra, $experience).', porque la necesita.';
            }

            return;
        }

        $dependents = $experience->dependentsOf($key, $this->featuresOff);
        if ($dependents === []) {
            $this->featuresOff[] = $key;

            return;
        }
        $this->pendingOff = $key;
        $this->pendingDependents = $dependents;
    }

    public function confirmOff(PersonalExperience $experience): void
    {
        if ($this->pendingOff !== null && array_key_exists($this->pendingOff, $experience->featureCatalog())) {
            $this->featuresOff = $experience->cascadeOff($this->pendingOff, $this->featuresOff);
        }
        $this->cancelOff();
    }

    public function cancelOff(): void
    {
        $this->pendingOff = null;
        $this->pendingDependents = [];
    }

    public function save(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $experience->saveModules($userId, $this->modules);
        $experience->saveFeatures($userId, $this->featuresOff);
        $this->redirectRoute('settings.section', ['section' => 'modules']);
    }

    public function featureLabel(string $key, PersonalExperience $experience): string
    {
        return $experience->featureCatalog()[$key]['label'] ?? $key;
    }

    /** @param  list<string>  $keys */
    public function labelList(array $keys, PersonalExperience $experience): string
    {
        $labels = array_map(fn ($key) => '«'.$this->featureLabel($key, $experience).'»', $keys);
        $last = array_pop($labels);

        return $labels ? implode(', ', $labels).' y '.$last : (string) $last;
    }

    public function render()
    {
        $experience = app(PersonalExperience::class);
        $features = [];
        foreach ($experience->featureCatalog() as $key => $definition) {
            $features[$definition['module']][$definition['feature']] = $definition;
        }

        return view('livewire.settings.module-preferences', [
            'catalog' => $experience->catalog(),
            'features' => $features,
            'experience' => $experience,
        ]);
    }
}
