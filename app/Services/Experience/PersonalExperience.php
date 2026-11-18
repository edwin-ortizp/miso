<?php

namespace App\Services\Experience;

use App\Models\ModuleSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Presentation preferences and resumable onboarding. Always scope explicitly. */
class PersonalExperience
{
    public const KEY = 'personal-experience';

    /** Módulos que nunca se ocultan ni se ofrecen como opcionales; «social» solo muestra un código y ajustes opt-in (CHG-122). */
    public const ALWAYS_VISIBLE = ['home', 'settings', 'social'];

    private array $states = [];

    public function catalog(): array
    {
        $items = [];
        foreach (config('modules.navigation', []) as $section) {
            foreach ($section['items'] as $item) {
                if (! isset($item['feature']) && ! in_array($item['module'], self::ALWAYS_VISIBLE, true)) {
                    $items[$item['module']] = ['label' => $item['label'], 'icon' => $item['icon'],
                        'description' => config('modules.'.$item['module'].'.subtitle', 'Organiza y consulta esta parte de tu día.')];
                }
            }
        }

        return $items;
    }

    public function state(int $userId): array
    {
        return $this->states[$userId] ??= ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', self::KEY)->first()?->settings ?? [];
    }

    public function visible(string $module, ?int $userId = null): bool
    {
        if (in_array($module, self::ALWAYS_VISIBLE, true)) {
            return true;
        }
        $state = $this->state($userId ?? (int) auth()->id());

        return ! isset($state['modules']) || in_array($module, $state['modules'], true);
    }

    public function navigation(?int $userId = null): array
    {
        $state = $this->state($userId ?? (int) auth()->id());
        $sections = config('modules.navigation');
        foreach ($sections as $key => &$section) {
            $section['items'] = array_values(array_filter($section['items'], fn ($item) => isset($item['feature'])
                ? $this->featureVisible($item['module'], $item['feature'], $userId)
                : (in_array($item['module'], self::ALWAYS_VISIBLE, true)
                    || ! isset($state['modules']) || in_array($item['module'], $state['modules'], true))));
            if (! $section['items']) {
                unset($sections[$key]);
            }
        }

        return $sections;
    }

    /**
     * CHG-139: funciones opcionales de un módulo (`config('modules.<módulo>.features')`).
     * Todas están activas salvo las que la persona apagó (`features_off`, claves `módulo.función`).
     *
     * @return array<string, array<string, mixed>>
     */
    public function features(string $module): array
    {
        return (array) config("modules.{$module}.features", []);
    }

    /** @return array<string, array<string, mixed>> Todas las funciones, con clave `módulo.función`. */
    public function featureCatalog(): array
    {
        $all = [];
        foreach (array_keys($this->catalog()) as $module) {
            foreach ($this->features($module) as $feature => $definition) {
                $all["{$module}.{$feature}"] = $definition + ['module' => $module, 'feature' => $feature];
            }
        }

        return $all;
    }

    /** @return list<string> Claves `módulo.función` apagadas por la persona. */
    public function featuresOff(?int $userId = null): array
    {
        $userId ??= (int) auth()->id();
        if ($userId === 0) {
            return [];
        }
        $off = $this->state($userId)['features_off'] ?? [];

        return array_values(array_filter((array) $off, 'is_string'));
    }

    public function featureVisible(string $module, string $feature, ?int $userId = null): bool
    {
        if (! isset($this->features($module)[$feature])) {
            return true;
        }

        return ! in_array("{$module}.{$feature}", $this->featuresOff($userId), true);
    }

    /** Pestañas de un módulo sin las funciones apagadas. */
    public function tabs(string $module, array $tabs, ?int $userId = null): array
    {
        $off = $this->featuresOff($userId);
        if ($off === []) {
            return $tabs;
        }

        return array_values(array_filter($tabs, fn ($tab) => empty($tab['feature']) || ! in_array("{$module}.{$tab['feature']}", $off, true)));
    }

    /**
     * Apaga `$key` y todo lo que dependa de ella (`requires`: todas; `requires_any`: al menos una).
     *
     * @param  list<string>  $off  Claves ya apagadas.
     * @return list<string> Conjunto apagado resultante.
     */
    public function cascadeOff(string $key, array $off): array
    {
        $off[] = $key;
        $catalog = $this->featureCatalog();
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($catalog as $candidate => $definition) {
                if (in_array($candidate, $off, true)) {
                    continue;
                }
                $module = $definition['module'];
                $needs = array_map(fn ($f) => "{$module}.{$f}", (array) ($definition['requires'] ?? []));
                $anyOf = array_map(fn ($f) => "{$module}.{$f}", (array) ($definition['requires_any'] ?? []));
                $missing = array_intersect($needs, $off) !== [];
                $noneLeft = $anyOf !== [] && array_diff($anyOf, $off) === [];
                if ($missing || $noneLeft) {
                    $off[] = $candidate;
                    $changed = true;
                }
            }
        }

        return array_values(array_unique($off));
    }

    /** Funciones que se apagarían además de `$key` (sin incluirla). */
    public function dependentsOf(string $key, array $off): array
    {
        return array_values(array_diff($this->cascadeOff($key, $off), $off, [$key]));
    }

    /**
     * Enciende `$key` y lo que necesita: sus `requires` y, si todas las alternativas de `requires_any`
     * están apagadas, la primera.
     *
     * @param  list<string>  $off
     * @return list<string> Conjunto apagado resultante.
     */
    public function cascadeOn(string $key, array $off): array
    {
        $catalog = $this->featureCatalog();
        $pending = [$key];
        while ($pending) {
            $current = array_shift($pending);
            $off = array_values(array_diff($off, [$current]));
            $definition = $catalog[$current] ?? null;
            if (! $definition) {
                continue;
            }
            $module = $definition['module'];
            foreach ((array) ($definition['requires'] ?? []) as $needed) {
                if (in_array("{$module}.{$needed}", $off, true)) {
                    $pending[] = "{$module}.{$needed}";
                }
            }
            $anyOf = array_map(fn ($f) => "{$module}.{$f}", (array) ($definition['requires_any'] ?? []));
            if ($anyOf !== [] && array_diff($anyOf, $off) === []) {
                $pending[] = $anyOf[0];
            }
        }

        return array_values($off);
    }

    /** Guarda las funciones apagadas. Valida las claves y deja el conjunto coherente con las dependencias. */
    public function saveFeatures(int $userId, array $off): array
    {
        $catalog = $this->featureCatalog();
        foreach ($off as $key) {
            if (! is_string($key) || ! array_key_exists($key, $catalog)) {
                throw ValidationException::withMessages(['features' => 'Elige funciones disponibles.']);
            }
        }
        $normalized = [];
        foreach (array_values(array_unique($off)) as $key) {
            $normalized = $this->cascadeOff($key, $normalized);
        }
        sort($normalized);
        $this->change($userId, fn ($state) => array_replace($state, ['features_off' => $normalized]));

        return $normalized;
    }

    public function required(int $userId): bool
    {
        $state = $this->state($userId);

        return ($state['required'] ?? false) && empty($state['completed_at']);
    }

    /** Serialize updates to a user's preferences; never overwrite another module's settings. */
    public function change(int $userId, callable $update): array
    {
        unset($this->states[$userId]);

        return DB::transaction(function () use ($userId, $update) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $row = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => self::KEY]);
            $row->user_id = $userId;
            $row->settings = $update($row->settings ?? []);
            $row->save();

            return $row->settings;
        });
    }

    public function begin(int $userId, bool $required = false): void
    {
        $this->change($userId, function ($state) use ($required) {
            if (isset($state['started_at'])) {
                return $state;
            }

            return $state + ['required' => $required, 'started_at' => now()->toIso8601String(), 'step' => 1,
                'modules' => $required ? ['habits', 'goals', 'tasks'] : array_keys($this->catalog()), 'events' => ['started' => now()->toIso8601String()]];
        });
    }

    /** Adapt only this person's guide on entry; preserve historical records and events. */
    public function prepareGuide(int $userId): void
    {
        $this->begin($userId);
        if (($this->state($userId)['guide_version'] ?? 0) === 2) {
            return;
        }
        $this->change($userId, function ($state) {
            if (($state['guide_version'] ?? 0) === 2) {
                return $state;
            }
            $state['contexts'] = match ($state['context'] ?? '') {
                'work' => ['work'], 'study' => ['study'], 'both' => ['work', 'study'], 'other' => ['none'], default => [],
            };
            $state['legacy_step'] = $state['step'] ?? 1;
            $state['guide_version'] = 2;
            $state['step'] = ! empty($state['completed_at']) ? 4 : (empty($state['contexts']) ? 1 : 2);

            return $state;
        });
    }

    public function saveModules(int $userId, array $modules): void
    {
        $catalog = $this->catalog();
        foreach ($modules as $module) {
            if (! is_string($module) || ! array_key_exists($module, $catalog)) {
                throw ValidationException::withMessages(['modules' => 'Elige módulos disponibles.']);
            }
        }
        $this->change($userId, fn ($state) => array_replace($state, ['modules' => array_values(array_unique($modules))]));
    }

    public function event(int $userId, string $name): void
    {
        $current = $this->state($userId);
        if (! isset($current['started_at']) || isset($current['events'][$name])) {
            return;
        }
        $this->change($userId, function ($state) use ($name) {
            if (! isset($state['started_at'])) {
                return $state;
            }
            $state['events'][$name] ??= now()->toIso8601String();

            return $state;
        });
    }
}
