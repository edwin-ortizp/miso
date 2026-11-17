<?php

namespace App\Livewire\Health;

use App\Models\HealthEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mapa corporal')]
class HealthBodyMap extends Component
{
    public const SORTS = [
        'count' => 'Más eventos',
        'recent' => 'Más recientes',
        'name' => 'Nombre',
    ];

    #[Url(as: 'range', history: true, keep: true)]
    public string $range = '1y';

    #[Url(as: 'status', history: true, keep: true)]
    public string $status = 'all';

    #[Url(as: 'types', history: true, keep: true)]
    public array $types = [];

    public string $sort = 'count';

    public ?string $selectedZone = null;

    public function selectZone(?string $zone): void
    {
        $this->selectedZone = array_key_exists($zone ?? '', HealthEvent::BODY_AREAS) ? $zone : null;
    }

    private int $eventTotal = 0;

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    public function updated(string $property): void
    {
        $this->normalizeFilters();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        match ($key) {
            'range' => $this->range = 'all',
            'status' => $this->status = 'all',
            'types' => $this->types = $value === null ? [] : array_values(array_diff($this->types, [$value])),
            default => null,
        };
    }

    public function clearFilters(): void
    {
        $this->range = 'all';
        $this->status = 'all';
        $this->types = [];
    }

    /**
     * @return list<array{key: string, value: ?string, label: string, icon: string}>
     */
    public function activeFilters(): array
    {
        $filters = [];
        if ($this->range !== 'all') {
            $filters[] = ['key' => 'range', 'value' => null, 'label' => HealthIndex::RANGES[$this->range], 'icon' => 'bi-calendar-range'];
        }
        if ($this->status !== 'all') {
            $filters[] = ['key' => 'status', 'value' => null, 'label' => HealthIndex::STATUSES[$this->status], 'icon' => 'bi-activity'];
        }
        foreach ($this->types as $type) {
            $filters[] = ['key' => 'types', 'value' => $type, 'label' => HealthEvent::TYPES[$type], 'icon' => 'bi-tag'];
        }

        return $filters;
    }

    public static function heatLevel(int $count): int
    {
        return match (true) {
            $count === 0 => 0,
            $count === 1 => 1,
            $count <= 3 => 2,
            $count <= 5 => 3,
            default => 4,
        };
    }

    public function render()
    {
        $zones = $this->zoneStats();
        $mapZones = collect(HealthEvent::BODY_AREAS)
            ->except(HealthEvent::OFF_MAP_BODY_AREAS)
            ->map(fn (string $label, string $key) => [
                'label' => $label,
                'count' => $zones[$key]['count'] ?? 0,
                'level' => $zones[$key]['level'] ?? 0,
                'last' => $zones[$key]['last'] ?? null,
                'href' => $zones[$key]['href'] ?? null,
            ]);

        return view('livewire.health.health-body-map', [
            'zones' => $this->sorted($zones),
            'guardianContext' => \App\Services\Game\Guardian\HealthContext::body($zones, $this->eventTotal, $this->selectedZone),
            'mapZones' => $mapZones,
            'total' => $this->eventTotal,
            'activeFilters' => $this->activeFilters(),
            'ranges' => HealthIndex::RANGES,
            'statuses' => HealthIndex::STATUSES,
            'typeLabels' => array_intersect_key(HealthEvent::TYPES, array_flip(HealthEvent::BODY_AREA_TYPES)),
            'sorts' => self::SORTS,
        ]);
    }

    /**
     * Eventos por zona del cuerpo dentro de los filtros activos.
     *
     * @return Collection<string, array{key: string, label: string, icon: string, count: int, level: int, onMap: bool, latest: CarbonImmutable, last: string, href: string}>
     */
    private function zoneStats(): Collection
    {
        $query = HealthEvent::query()->whereNotNull('details');

        if ($this->types !== []) {
            $query->whereIn('type', $this->types);
        }
        if ($since = $this->rangeStart()) {
            $query->whereDate('event_date', '>=', $since);
        }
        if ($this->status === 'active') {
            $query->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNull('end_date');
        } elseif ($this->status === 'recovered') {
            $query->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNotNull('end_date');
        }

        $events = $query->whereIn('type', HealthEvent::BODY_AREA_TYPES)->orderByDesc('event_date')->get()
            ->filter(fn (HealthEvent $event) => $event->bodyAreas() !== []);
        $this->eventTotal = $events->count();

        // Un evento con varias zonas cuenta una vez en cada una.
        $byZone = [];
        foreach ($events as $event) {
            foreach ($event->bodyAreas() as $area) {
                $byZone[$area][] = $event;
            }
        }

        return collect($byZone)->map(function (array $zoneEvents, string $key): array {
            /** @var HealthEvent $latest */
            $latest = $zoneEvents[0];
            $count = count($zoneEvents);

            return [
                'key' => $key,
                'label' => HealthEvent::BODY_AREAS[$key],
                'icon' => HealthEvent::bodyAreaIcon($key),
                'count' => $count,
                'level' => self::heatLevel($count),
                'onMap' => ! in_array($key, HealthEvent::OFF_MAP_BODY_AREAS, true),
                'latest' => CarbonImmutable::parse($latest->event_date),
                'last' => $latest->title.' · '.$latest->event_date->locale('es')->diffForHumans(),
                'href' => route('health', array_filter(['zone' => $key, 'range' => $this->range === 'all' ? null : $this->range])),
            ];
        });
    }

    private function sorted(Collection $zones): Collection
    {
        return match ($this->sort) {
            'recent' => $zones->sortByDesc('latest'),
            'name' => $zones->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE),
            default => $zones->sortBy([['count', 'desc'], ['latest', 'desc']]),
        };
    }

    private function rangeStart(): ?CarbonImmutable
    {
        $today = CarbonImmutable::today();

        return match ($this->range) {
            '30d' => $today->subDays(29),
            '60d' => $today->subDays(59),
            '6m' => $today->subMonths(6),
            '1y' => $today->subYear(),
            default => null,
        };
    }

    private function normalizeFilters(): void
    {
        if (! array_key_exists($this->range, HealthIndex::RANGES)) {
            $this->range = '1y';
        }
        if (! array_key_exists($this->status, HealthIndex::STATUSES)) {
            $this->status = 'all';
        }
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'count';
        }
        $this->types = array_values(array_unique(array_filter(
            array_map('strval', (array) $this->types),
            fn (string $type) => in_array($type, HealthEvent::BODY_AREA_TYPES, true),
        )));
    }
}
