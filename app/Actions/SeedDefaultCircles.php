<?php

namespace App\Actions;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Str;

/** Gives a user the starting circles; they stay editable from Relaciones. */
class SeedDefaultCircles
{
    public const NAMES = [
        'Pareja',
        'Familia',
        'Amigos',
        'Trabajo',
    ];

    public static function for(User $user): void
    {
        $existing = Circle::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->pluck('name')
            ->map(fn (string $name) => Str::lower($name))
            ->all();

        foreach (self::NAMES as $index => $name) {
            if (in_array(Str::lower($name), $existing, true)) {
                continue;
            }

            (new Circle)->forceFill([
                'user_id' => $user->id,
                'name' => $name,
                'sort_order' => $index + 1,
            ])->save();
        }
    }
}
