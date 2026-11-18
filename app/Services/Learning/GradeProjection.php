<?php

namespace App\Services\Learning;

class GradeProjection
{
    private const FULL_WEIGHT = 100_000_000;

    /** Weights and grades are stored in hundredths. Products remain exact in both grading modes. */
    public function calculate(object $book, iterable $cuts, iterable $evaluations, array $scenario = []): array
    {
        $cutWeights = [];
        $insideCuts = [];
        foreach ($cuts as $cut) {
            $cutWeights[$cut->id] = $cut->weight;
            $insideCuts[$cut->id] = 0;
        }

        $sum = $evaluated = $known = $missingScores = 0;
        $unassigned = false;
        foreach ($evaluations as $entry) {
            $cutWeight = $book->mode === 'cuts' ? ($cutWeights[$entry->cut_id] ?? null) : 10_000;
            $unassigned = $unassigned || $cutWeight === null || $entry->weight === null;
            $score = $entry->score ?? $scenario[$entry->id] ?? null;
            $missingScores += (int) ($score === null);
            if ($cutWeight === null || $entry->weight === null) {
                continue;
            }
            if ($book->mode === 'cuts') {
                $insideCuts[$entry->cut_id] += $entry->weight;
            }
            $effective = $entry->weight * $cutWeight;
            $known += $effective;
            if ($score !== null) {
                $evaluated += $effective;
                $sum += $score * $effective;
            }
        }

        $setup = $book->mode === 'cuts' ? array_sum($cutWeights) : intdiv($known, 10_000);
        $complete = ! $unassigned && $setup === 10_000 && $known === self::FULL_WEIGHT
            && ! array_filter($insideCuts, fn ($weight) => $weight !== 10_000);
        $result = [
            'state' => 'setup', 'evaluated' => $evaluated / 1_000_000,
            'average' => $evaluated ? $sum / $evaluated : null,
            'setup' => $setup, 'insideCuts' => $insideCuts, 'unassigned' => $unassigned,
            'value' => null, 'urgency' => null, 'rounding' => false,
        ];
        if (! $complete) {
            return $result;
        }

        $remaining = self::FULL_WEIGHT - $evaluated;
        $target = $book->passing * self::FULL_WEIGHT;
        if ($missingScores === 0) {
            return array_replace($result, [
                'state' => $sum >= $target ? 'passed' : 'failed', 'value' => $sum / self::FULL_WEIGHT,
                'rounding' => $sum < $target && round($sum / self::FULL_WEIGHT) === (float) $book->passing,
            ]);
        }

        $maximum = $sum + $remaining * $book->maximum;
        if ($maximum < $target) {
            return array_replace($result, [
                'state' => 'impossible', 'value' => $maximum / self::FULL_WEIGHT, 'urgency' => 2,
                'rounding' => round($maximum / self::FULL_WEIGHT) === (float) $book->passing,
            ]);
        }
        if ($sum + $remaining * $book->minimum >= $target) {
            return array_replace($result, ['state' => 'secured']);
        }

        $gap = $target - $sum;
        $needed = intdiv($gap, $remaining) + (int) ($gap % $remaining > 0);
        $scaleProgress = ($needed - $book->minimum) / ($book->maximum - $book->minimum);

        return array_replace($result, ['state' => 'needed', 'value' => $needed, 'urgency' => $scaleProgress >= 0.75 ? 1 : null]);
    }

    public static function number(int|float $hundredths): string
    {
        return number_format($hundredths / 100, 2, ',', '.');
    }

    public static function percent(int|float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 6, ',', ''), '0'), ',').' %';
    }

    public static function headline(array $result): string
    {
        $value = $result['value'] === null ? '' : self::number($result['value']);

        return match ($result['state']) {
            'passed' => "Aprobada · {$value}",
            'failed' => "No aprobada · {$value}",
            'needed' => "Necesitas {$value} para pasar",
            'impossible' => "Con estas ponderaciones, el máximo posible es {$value}",
            'secured' => 'Ya alcanzaste el mínimo',
            default => 'Completa las ponderaciones para ver la proyección',
        };
    }
}
