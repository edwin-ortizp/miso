<?php

namespace App\Services\Game\Guardian;

use App\Models\Relationship;
use App\Models\RelationshipEvent;
use Carbon\CarbonInterface;

/** Consejos de Relaciones (CHG-064): cumpleaños en ≤ 7 días y alguien sin registros hace ≥ 60 días. 2 consultas. */
class RelationshipAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $people = Relationship::query()->where('is_archived', false)
            ->get(['id', 'full_name', 'nickname', 'birthday_month', 'birthday_day']);
        if ($people->isEmpty()) {
            return [];
        }
        $name = fn ($p) => $p->nickname ?: $p->full_name;

        $birthday = $people->filter(fn ($p) => $p->birthday_month && $p->birthday_day)->map(function ($p) use ($today) {
            $on = function (int $year) use ($p, $today) {
                $month = $today->copy()->setDate($year, (int) $p->birthday_month, 1);

                return $month->day(min((int) $p->birthday_day, $month->daysInMonth));
            };
            $next = $on($today->year);
            if ($next->lt($today)) {
                $next = $on($today->year + 1);
            }

            return [$p, (int) $today->diffInDays($next)];
        })->filter(fn ($x) => $x[1] <= 7)->sortBy(fn ($x) => $x[1])->first();
        if ($birthday) {
            $days = $birthday[1];
            $when = match (true) { $days === 0 => 'hoy', $days === 1 => 'mañana', default => 'el '.$today->copy()->addDays($days)->translatedFormat('l') };
            $out[] = $this->candidate('relationships', 'birthday', ['person' => $name($birthday[0]), 'when' => $when], 3, ['person']);
        }

        $events = RelationshipEvent::query()->whereIn('relationship_id', $people->pluck('id'))
            ->selectRaw('relationship_id, COUNT(*) as total, MAX(event_date) as last_date')
            ->groupBy('relationship_id')->having('total', '>=', 2)->get();
        $distant = $events->map(fn ($e) => [$e, (int) \Illuminate\Support\Carbon::parse($e->last_date)->startOfDay()->diffInDays($today)])
            ->filter(fn ($x) => $x[1] >= 60)->sortByDesc(fn ($x) => $x[1])->first();
        if ($distant && ($person = $people->firstWhere('id', $distant[0]->relationship_id))) {
            $out[] = $this->candidate('relationships', 'distance', ['days' => $distant[1], 'person' => $name($person)], 2, ['person']);
        }

        return $out;
    }
}
