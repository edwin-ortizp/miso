<?php

namespace App\Support;

/** Texto plano. El historial solo vive en la sesión y se separa por usuario y contexto. */
class CompanionVoice
{
    /** @return array{id: string, text: string} */
    public static function pick(int $userId, string $group, array $variables = [], ?string $exceptId = null): array
    {
        return self::fromPool($userId, $group, config("companion_voice.{$group}", []), $variables, $exceptId);
    }

    /** @param array<string, string> $pool @return array{id: string, text: string} */
    public static function fromPool(int $userId, string $group, array $pool, array $variables = [], ?string $exceptId = null): array
    {
        $pool = array_filter($pool, fn ($text) => is_string($text) && $text !== '');
        if ($pool === []) {
            return ['id' => 'fallback', 'text' => 'Registro actualizado.'];
        }

        $session = app()->bound('session') && session()->isStarted() ? session() : null;
        $key = "companion_voice.{$userId}.{$group}";
        $history = array_values((array) ($session?->get($key, []) ?? []));
        $eligible = array_diff_key($pool, array_fill_keys($exceptId === null ? [] : [$exceptId], true)) ?: $pool;
        $recent = $history;
        do {
            $available = array_diff_key($eligible, array_fill_keys($recent, true));
            if ($available !== []) {
                break;
            }
            array_shift($recent);
        } while ($recent !== []);
        $available = $available ?: $eligible;
        $id = (string) array_rand($available);
        $session?->put($key, array_slice([...$history, $id], -3));

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{'.$name.'}'] = (string) $value;
        }

        return ['id' => $id, 'text' => strtr($available[$id], $replacements)];
    }
}
