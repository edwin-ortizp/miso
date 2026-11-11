<?php

namespace App\Services\Game\Guardian;

use App\Models\JournalEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Consejos del Diario (CHG-063): días sin escribir, racha y un recuerdo con una
 * cita corta (≤ 80 caracteres, escapada) de hace un mes o un año. 3 consultas.
 */
class JournalAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();

        $last = JournalEntry::query()->max('date');
        if ($last) {
            $gap = (int) Carbon::parse($last)->startOfDay()->diffInDays($today);
            if ($gap >= 2) {
                $out[] = $this->candidate('journal', 'gap', ['days' => $gap], 3);
            }
        }

        $dates = JournalEntry::query()->where('date', '>=', $today->copy()->subDays(60))
            ->pluck('date')->map(fn ($d) => $d->toDateString())->unique()->flip();
        $streak = 0;
        for ($d = isset($dates[$today->toDateString()]) ? 0 : 1; isset($dates[$today->copy()->subDays($d)->toDateString()]); $d++) {
            $streak++;
        }
        if ($streak >= 3) {
            $out[] = $this->candidate('journal', 'streak', ['days' => $streak], 2);
        }

        $memory = JournalEntry::query()
            ->where(fn ($q) => $q->whereBetween('date', [$today->copy()->subDays(35), $today->copy()->subDays(28)])
                ->orWhereBetween('date', [$today->copy()->subYear()->subDays(3), $today->copy()->subYear()->addDays(3)]))
            ->whereNotNull('text')->inRandomOrder()->first(['date', 'text']);
        $quote = $memory ? Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $memory->text))), 80, '…', true) : '';
        if ($quote !== '') {
            $when = $memory->date->lt($today->copy()->subDays(300)) ? 'Hace un año' : 'Hace un mes';
            $out[] = $this->candidate('journal', 'memory', ['when' => $when, 'quote' => $quote], 2);
        }

        return $out;
    }
}
