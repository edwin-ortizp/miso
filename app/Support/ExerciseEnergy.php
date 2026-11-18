<?php

namespace App\Support;

class ExerciseEnergy
{
    // 2024 Adult Compendium, speed in mph.
    // https://pacompendium.com/walking/ — level walking, codes 17151–17231.
    private const WALK = [
        [2, 2.4, 2.8], [2.5, 2.5, 3.0], [2.8, 3.4, 3.8],
        [3.5, 3.9, 4.8], [4, 4.4, 5.5], [4.5, 4.9, 7.0], [5, 5.5, 8.5],
    ];

    // https://pacompendium.com/running/ — speed-specific running, codes 12028–12135.
    private const RUN = [
        [4, 4.2, 6.5], [4.3, 4.8, 7.8], [5, 5.2, 8.5], [5.5, 5.8, 9.0],
        [6, 6.3, 9.3], [6.7, 6.7, 10.5], [7, 7, 11.0], [7.5, 7.5, 11.8],
        [8, 8, 12.0], [8.6, 8.6, 12.5], [9, 9, 13.0], [9.3, 9.6, 14.8],
        [10, 10, 14.8], [11, 11, 16.8], [12, 12, 18.5], [13, 13, 19.8], [14, 14, 23.0],
    ];

    public static function met(string $profile, float $kmPerHour): ?float
    {
        $ranges = match ($profile) {
            'walk' => self::WALK,
            'jog' => [[2.6, 3.7, 3.3], ...self::RUN], // Code 12026.
            'run' => self::RUN,
            default => [],
        };
        $mph = $kmPerHour / 1.609344;
        if ($profile === 'walk' && $mph > 0 && $mph < 2) {
            return 2.3; // Code 17151.
        }
        if ($ranges === [] || $mph < $ranges[0][0] || $mph > $ranges[array_key_last($ranges)][1]) {
            return null;
        }

        $nearest = INF;
        $met = null;
        foreach ($ranges as [$min, $max, $value]) {
            $gap = max($min - $mph, $mph - $max, 0);
            if ($gap < $nearest) {
                $nearest = $gap;
                $met = $value;
            }
        }

        return $met;
    }
}
