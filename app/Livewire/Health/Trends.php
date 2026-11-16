<?php

namespace App\Livewire\Health;

use App\Models\HealthMeasurement;
use App\Support\HealthTrends;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** CHG-128: evolución de las mediciones y del sueño. Describe lo registrado; no diagnostica ni afirma causas. */
#[Layout('layouts.app')]
#[Title('Tendencias de salud')]
class Trends extends Component
{
    #[Url(as: 'days', history: true, except: '30')]
    public string $days = '30';

    public function updatedDays(): void
    {
        $this->normalize();
    }

    public function setDays(string $days): void
    {
        $this->days = $days;
        $this->normalize();
    }

    private function normalize(): void
    {
        if (! array_key_exists((int) $this->days, HealthTrends::PERIODS)) {
            $this->days = '30';
        }
    }

    public function mount(): void
    {
        $this->normalize();
    }

    public function render()
    {
        $days = (int) $this->days;

        $charts = collect(array_keys(HealthMeasurement::TYPES))
            ->map(fn (string $type) => HealthTrends::measurement($type, $days))
            ->filter(fn (array $chart) => $chart['summary']['count'] > 0)
            ->values();
        $sleep = HealthTrends::sleep($days);

        return view('livewire.health.trends', [
            'charts' => $charts,
            'sleep' => $sleep['summary']['count'] > 0 ? $sleep : null,
            'insight' => HealthTrends::sleepEnergy($days),
            'periods' => HealthTrends::PERIODS,
            'selected' => $days,
        ]);
    }
}
