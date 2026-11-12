<?php

namespace App\Livewire\Journal;

use App\Models\JournalEntry;
use App\Support\LifeCalendar;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Diario: Vida')]
class JournalLifeCalendar extends Component
{
    public function render()
    {
        $user = auth()->user();
        $bounds = $user ? LifeCalendar::boundsFor($user) : null;

        if (! $bounds) {
            return view('livewire.journal.journal-life-calendar', ['isConfigured' => false]);
        }

        $weekCells = $this->buildWeekCells($bounds);
        $currentWeek = now()->startOfWeek(Carbon::MONDAY);
        $totalWeeks = (int) $bounds['start']->diffInWeeks($bounds['end']) + 1;
        $lastLivedWeek = $currentWeek->lt($bounds['start']) ? $bounds['start']->copy()->subWeek() : $currentWeek->min($bounds['end']);
        $livedWeeks = $lastLivedWeek->lt($bounds['start']) ? 0 : (int) $bounds['start']->diffInWeeks($lastLivedWeek) + 1;

        return view('livewire.journal.journal-life-calendar', [
            'isConfigured' => true,
            'weekRows' => collect($weekCells)->groupBy(fn (array $cell) => $cell['iso_year']),
            'currentYear' => now()->isoWeekYear,
            'totalWeeks' => $totalWeeks,
            'livedWeeks' => $livedWeeks,
            'remainingWeeks' => $totalWeeks - $livedWeeks,
        ]);
    }

    /**
     * CHG-095: son miles de celdas. La etiqueta, el nivel y el enlace se arman aquí con
     * fechas nativas, los meses traducidos una sola vez y una plantilla de URL, en vez de
     * dos `translatedFormat` y un `route()` por celda en la vista.
     *
     * @return array<int, array{key: string, iso_year: int, entries_count: int, is_future: bool, is_current: bool, level: int, label: string, href: ?string}>
     */
    private function buildWeekCells(array $bounds): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = Carbon::create(2001, $month, 1)->translatedFormat('M');
        }
        $hrefTemplate = route('journal.life.week', ['week' => '__WEEK__']);
        $day = fn (\DateTimeImmutable $date) => $date->format('d').' '.$months[(int) $date->format('n')];

        $entriesByWeek = JournalEntry::whereBetween('date', [
            $bounds['start']->toDateString(),
            $bounds['end']->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        ])->get()->countBy(fn (JournalEntry $entry) => sprintf('%d-W%02d', $entry->date->isoWeekYear, $entry->date->isoWeek));

        $currentWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $last = $bounds['end']->toDateString();
        $cells = [];
        // `boundsFor` entrega lunes: cada celda va de lunes a domingo.
        $cursor = new \DateTimeImmutable($bounds['start']->toDateString());

        while (($monday = $cursor->format('Y-m-d')) <= $last) {
            $isoYear = (int) $cursor->format('o');
            $key = sprintf('%d-W%02d', $isoYear, (int) $cursor->format('W'));
            $count = $entriesByWeek->get($key, 0);
            $isFuture = $monday > $currentWeek;
            $cells[] = [
                'key' => $key,
                'iso_year' => $isoYear,
                'entries_count' => $count,
                'is_future' => $isFuture,
                'is_current' => $monday === $currentWeek,
                'level' => $count === 0 ? 0 : ($count <= 3 ? 1 : ($count <= 6 ? 2 : 3)),
                'label' => sprintf('Semana %s, del %s al %s, %d día%s con entrada%s', $key, $day($cursor), $day($cursor->modify('+6 days')),
                    $count, $count === 1 ? '' : 's', $isFuture ? '. Aún no disponible.' : ''),
                'href' => $isFuture ? null : str_replace('__WEEK__', $key, $hrefTemplate),
            ];
            $cursor = $cursor->modify('+7 days');
        }

        return $cells;
    }
}
