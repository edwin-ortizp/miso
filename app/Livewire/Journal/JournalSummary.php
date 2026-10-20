<?php

namespace App\Livewire\Journal;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Línea de tiempo condensada del diario: solo las entradas con resumen, de la más reciente a la más antigua.
 */
#[Layout('layouts.app')]
#[Title('Resumen del diario')]
class JournalSummary extends Component
{
    use WithManagementCard;

    public const PERIODS = [
        '1m' => 'Último mes',
        '6m' => 'Últimos 6 meses',
        '1y' => 'Último año',
        'all' => 'Todo el historial',
        'custom' => 'Personalizado',
    ];

    #[Url(as: 'period', history: true, keep: true)]
    public string $period = '1m';

    #[Url(as: 'from', history: true, except: '')]
    public string $from = '';

    #[Url(as: 'to', history: true, except: '')]
    public string $to = '';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'period', 'from', 'to'], true)) {
            $this->normalizeFilters();
            $this->resetPage();
        }
    }

    public function applyFilters(string $period, ?string $from = null, ?string $to = null): void
    {
        $this->period = $period;
        $this->from = (string) $from;
        $this->to = (string) $to;
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->period = '1m';
        $this->from = '';
        $this->to = '';
        $this->resetPage();
    }

    public function removeFilter(string $key): void
    {
        match ($key) {
            'period' => [$this->period, $this->from, $this->to] = ['all', '', ''],
            default => null,
        };
        $this->resetPage();
    }

    public function render()
    {
        $entries = $this->query()
            ->orderByDesc('date')
            ->paginate($this->perPage(), ['id', 'date', 'summary']);

        return view('livewire.journal.journal-summary', [
            'entries' => $entries,
            'totalCount' => JournalEntry::query()->whereNotNull('summary')->where('summary', '!=', '')->count(),
            'periods' => self::PERIODS,
            'periodLabel' => $this->periodLabel(),
        ]);
    }

    private function query(): Builder
    {
        [$start, $end] = $this->bounds();

        return JournalEntry::query()
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->when($start, fn (Builder $q) => $q->whereDate('date', '>=', $start->toDateString()))
            ->when($end, fn (Builder $q) => $q->whereDate('date', '<=', $end->toDateString()))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('summary', 'like', '%'.trim($this->search).'%'));
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function bounds(): array
    {
        $today = CarbonImmutable::today();

        return match ($this->period) {
            '1m' => [$today->subMonth(), $today],
            '6m' => [$today->subMonths(6), $today],
            '1y' => [$today->subYear(), $today],
            'custom' => [$this->parseDate($this->from), $this->parseDate($this->to)],
            default => [null, null],
        };
    }

    private function periodLabel(): string
    {
        if ($this->period !== 'custom') {
            return self::PERIODS[$this->period];
        }

        [$start, $end] = $this->bounds();

        return match (true) {
            $start && $end => $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y'),
            (bool) $start => 'Desde '.$start->translatedFormat('j M Y'),
            (bool) $end => 'Hasta '.$end->translatedFormat('j M Y'),
            default => self::PERIODS['all'],
        };
    }

    private function normalizeFilters(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = '1m';
        }

        if ($this->period !== 'custom') {
            $this->from = '';
            $this->to = '';

            return;
        }

        $this->from = $this->parseDate($this->from)?->toDateString() ?? '';
        $this->to = $this->parseDate($this->to)?->toDateString() ?? '';

        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
