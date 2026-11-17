<?php

namespace App\Livewire\Vehicle;

use App\Livewire\Concerns\ManagesUserCategories;
use App\Models\MaintenanceTemplate;
use App\Models\VehicleMaintenancePlan;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Catálogo de mantenimientos')]
class VehicleCatalog extends Component
{
    use ManagesUserCategories;
    use WithManagementCard;

    public const SECTIONS = [
        'templates' => ['label' => 'Plantillas', 'icon' => 'bi-tools', 'route' => 'vehicles.catalog'],
        'categories' => ['label' => 'Categorías', 'icon' => 'bi-diagram-3', 'route' => 'vehicles.catalog.categories'],
    ];

    #[Locked]
    public string $section = 'templates';

    public bool $showTemplateForm = false;

    public ?string $editingTemplateId = null;

    public string $templateName = '';

    public string $templateCategory = '';

    public string $templateDescription = '';

    public array $templateVehicleTypes = [];

    public array $templatePowerSources = [];

    public array $templateTransmissionTypes = [];

    public ?int $templateDefaultIntervalDays = null;

    public ?float $templateDefaultIntervalUsage = null;

    public string $catalogMessage = '';

    #[Url(as: 'q', history: true, keep: true)]
    public string $catalogSearch = '';

    #[Url(as: 'category', history: true, keep: true)]
    public string $catalogCategory = '';

    #[Url(as: 'source', history: true, keep: true)]
    public string $catalogSource = '';

    #[Url(as: 'vehicle_type', history: true, keep: true)]
    public string $catalogVehicleType = '';

    #[Url(as: 'power', history: true, keep: true)]
    public string $catalogPowerSource = '';

    #[Url(as: 'transmission', history: true, keep: true)]
    public string $catalogTransmissionType = '';

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'catalog')) {
            $this->resetPage();
        }
    }

    public function clearCatalogFilters(): void
    {
        $this->reset('catalogSearch', 'catalogCategory', 'catalogSource', 'catalogVehicleType', 'catalogPowerSource', 'catalogTransmissionType');
        $this->resetPage();
    }

    public function openTemplateForm(?string $id = null): void
    {
        $this->resetTemplateForm();
        if ($id && ($template = MaintenanceTemplate::query()->where('user_id', auth()->id())->find($id))) {
            $this->editingTemplateId = $template->id;
            $this->templateName = $template->name;
            $this->templateCategory = $template->category;
            $this->templateDescription = $template->description ?? '';
            $this->templateVehicleTypes = $template->vehicle_types ?? [];
            $this->templatePowerSources = $template->power_sources ?? [];
            $this->templateTransmissionTypes = $template->transmission_types ?? [];
            $this->templateDefaultIntervalDays = $template->default_interval_days;
            $this->templateDefaultIntervalUsage = $template->default_interval_usage === null ? null : (float) $template->default_interval_usage;
        }
        $this->showTemplateForm = true;
    }

    public function saveTemplate(): void
    {
        $template = $this->editingTemplateId
            ? MaintenanceTemplate::query()->where('user_id', auth()->id())->findOrFail($this->editingTemplateId)
            : new MaintenanceTemplate(['user_id' => auth()->id()]);
        $data = $this->validate([
            'templateName' => ['required', 'string', 'max:120', Rule::unique('maintenance_templates', 'name')->where(fn ($query) => $query->where('user_id', auth()->id()))->ignore($template->id)],
            'templateCategory' => ['required', 'string', 'max:80'], 'templateDescription' => ['nullable', 'string', 'max:1000'],
            'templateVehicleTypes' => ['array'], 'templateVehicleTypes.*' => ['in:automovil,motocicleta,bicicleta,patineta,otro'],
            'templatePowerSources' => ['array'], 'templatePowerSources.*' => ['in:gasolina,diesel,electrico,hibrido,humana,ninguna'],
            'templateTransmissionTypes' => ['array'], 'templateTransmissionTypes.*' => ['in:manual,automatica,cvt,automatizada,no_aplica'],
            'templateDefaultIntervalDays' => ['nullable', 'integer', 'min:1'], 'templateDefaultIntervalUsage' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $template->fill([
            'name' => $data['templateName'], 'category' => $data['templateCategory'], 'description' => $data['templateDescription'] ?: null,
            'vehicle_types' => $data['templateVehicleTypes'] ?: null, 'power_sources' => $data['templatePowerSources'] ?: null,
            'transmission_types' => $data['templateTransmissionTypes'] ?: null, 'default_interval_days' => $data['templateDefaultIntervalDays'],
            'default_interval_usage' => $data['templateDefaultIntervalUsage'],
        ])->save();
        $this->showTemplateForm = false;
        $this->catalogMessage = $this->editingTemplateId ? 'Plantilla actualizada.' : 'Plantilla creada.';
        $this->resetTemplateForm();
    }

    public function deleteTemplate(string $id): void
    {
        $template = MaintenanceTemplate::query()->where('user_id', auth()->id())->find($id);
        if (! $template) {
            $this->catalogMessage = 'Solo puedes eliminar tus propias plantillas.';

            return;
        }
        if (VehicleMaintenancePlan::query()->where('maintenance_template_id', $template->id)->exists()) {
            $this->catalogMessage = 'No se puede eliminar una plantilla con planes de mantenimiento asociados.';

            return;
        }
        $template->delete();
        $this->catalogMessage = 'Plantilla eliminada.';
    }

    private function visibleTemplates()
    {
        return MaintenanceTemplate::availableTo((int) auth()->id());
    }

    public function mount(string $section = 'templates'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
        $this->useCategoryDomain('maintenance');
    }

    private function resetTemplateForm(): void
    {
        $this->reset('editingTemplateId', 'templateName', 'templateCategory', 'templateDescription', 'templateVehicleTypes', 'templatePowerSources', 'templateTransmissionTypes', 'templateDefaultIntervalDays', 'templateDefaultIntervalUsage');
    }

    public function render()
    {
        $catalogCategories = \App\Support\CategoryCatalog::options('maintenance');
        $query = $this->visibleTemplates();
        if (trim($this->catalogSearch) !== '') {
            $search = '%'.trim($this->catalogSearch).'%';
            $query->where(fn ($templates) => $templates->where('name', 'like', $search)->orWhere('description', 'like', $search));
        }
        if ($this->catalogCategory !== '') {
            $query->whereIn('category', \App\Support\CategoryCatalog::expand('maintenance', [$this->catalogCategory]));
        }
        if ($this->catalogSource === 'base') {
            $query->whereNull('user_id');
        }
        if ($this->catalogSource === 'personal') {
            $query->where('user_id', auth()->id());
        }
        if ($this->catalogVehicleType !== '') {
            $query->where(fn ($templates) => $templates->whereNull('vehicle_types')->orWhereJsonContains('vehicle_types', $this->catalogVehicleType));
        }
        if ($this->catalogPowerSource !== '') {
            $query->where(fn ($templates) => $templates->whereNull('power_sources')->orWhereJsonContains('power_sources', $this->catalogPowerSource));
        }
        if ($this->catalogTransmissionType !== '') {
            $query->where(fn ($templates) => $templates->whereNull('transmission_types')->orWhereJsonContains('transmission_types', $this->catalogTransmissionType));
        }
        $catalogTemplates = $query->orderBy('category')->orderBy('name')->paginate($this->perPage());

        return view('livewire.vehicle.vehicle-catalog', ['catalogTemplates' => $catalogTemplates, 'catalogCategories' => $catalogCategories, 'sections' => self::SECTIONS]
            + ($this->section === 'categories' ? $this->categoryViewData() : []));
    }
}
