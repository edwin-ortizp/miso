<?php

namespace App\Livewire\Health;

use App\Actions\LogHealthMeasurement;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\HealthMeasurement;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CHG-122: mediciones de salud con unidad (presión arterial primero). Registra y muestra
 * historial; no diagnostica. Los nombres `value`, `value2` y `pulse` coinciden con las claves
 * de LogHealthMeasurement::validate para que cada error caiga en su campo.
 */
#[Layout('layouts.app')]
#[Title('Mediciones de salud')]
class HealthMeasurements extends Component
{
    use WithManagementCard;

    public const PERIODS = [
        'all' => 'Todo',
        '7' => 'Últimos 7 días',
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 90 días',
    ];

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
    ];

    #[Url(as: 'period', history: true, except: 'all')]
    public string $period = 'all';

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    public bool $showForm = false;
    public ?string $editingId = null;

    public string $type = 'blood_pressure';
    public string $value = '';
    public string $value2 = '';
    public string $pulse = '';
    public string $measuredAt = '';
    public string $notes = '';
    public bool $isSensitive = false;

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'all';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'recent';
        }
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->updatedPeriod();
    }

    public function clearFilters(): void
    {
        $this->reset('period');
        $this->resetPage();
    }

    public function applyFilters(string $period): void
    {
        $this->setPeriod($period);
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->measuredAt = now()->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $measurement = HealthMeasurement::findOrFail($id);

        $this->resetForm();
        $this->editingId = $measurement->id;
        $this->type = $measurement->type;
        $this->value = $this->plain($measurement->value);
        $this->value2 = $measurement->value2 !== null ? $this->plain($measurement->value2) : '';
        $this->pulse = $measurement->pulse !== null ? (string) $measurement->pulse : '';
        $this->measuredAt = $measurement->measured_at->format('Y-m-d\TH:i');
        $this->notes = (string) $measurement->notes;
        $this->isSensitive = $measurement->is_sensitive;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate(['measuredAt' => ['required', 'date']], [
            'measuredAt.required' => 'Indica la fecha y la hora de la medición.',
            'measuredAt.date' => 'Indica una fecha y hora válidas.',
        ]);

        $data = LogHealthMeasurement::validate([
            'type' => $this->type,
            'value' => $this->value === '' ? null : str_replace(',', '.', $this->value),
            'value2' => $this->value2 === '' ? null : str_replace(',', '.', $this->value2),
            'pulse' => $this->pulse === '' ? null : $this->pulse,
            'notes' => $this->notes === '' ? null : $this->notes,
            'is_sensitive' => $this->isSensitive,
        ]);

        $measuredAt = Carbon::parse($this->measuredAt, config('app.timezone'));

        if ($this->editingId) {
            LogHealthMeasurement::update(HealthMeasurement::findOrFail($this->editingId), $data, $measuredAt);
        } else {
            LogHealthMeasurement::handle($data, $measuredAt, 'app');
        }

        $this->closeForm();
        $this->resetPage();
    }

    public function delete(string $id): void
    {
        HealthMeasurement::where('id', $id)->delete();
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->type = 'blood_pressure';
        $this->value = '';
        $this->value2 = '';
        $this->pulse = '';
        $this->measuredAt = '';
        $this->notes = '';
        $this->isSensitive = false;
    }

    private function plain(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        $base = HealthMeasurement::query();
        $totalMeasurements = (clone $base)->count();
        $latest = (clone $base)->orderByDesc('measured_at')->first();

        $filtered = (clone $base)
            ->when($this->period !== 'all', fn ($q) => $q->where('measured_at', '>=', now()->subDays((int) $this->period)->startOfDay()));

        $measurements = $filtered
            ->orderBy('measured_at', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());

        return view('livewire.health.health-measurements', [
            'measurements' => $measurements,
            'totalMeasurements' => $totalMeasurements,
            'latest' => $latest,
            'activeFilters' => $this->period !== 'all'
                ? [['key' => 'period', 'value' => null, 'label' => 'Periodo: '.self::PERIODS[$this->period], 'icon' => 'bi-calendar-event']]
                : [],
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'definition' => HealthMeasurement::TYPES[$this->type] ?? HealthMeasurement::TYPES['blood_pressure'],
        ]);
    }
}
