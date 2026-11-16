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

    /** Módulos que nunca se ocultan ni se ofrecen como opcionales; «social» solo muestra un código y ajustes opt-in (CHG-121). */
    public const ALWAYS_VISIBLE = ['home', 'settings', 'social'];

    private array $states = [];

    public function catalog(): array
    {
        $items = [];
        foreach (config('modules.navigation', []) as $section) {
            foreach ($section['items'] as $item) {
                if (! in_array($item['module'], self::ALWAYS_VISIBLE, true)) {
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
            $section['items'] = array_values(array_filter($section['items'], fn ($item) => in_array($item['module'], self::ALWAYS_VISIBLE, true)
                || ! isset($state['modules']) || in_array($item['module'], $state['modules'], true)));
            if (! $section['items']) {
                unset($sections[$key]);
            }
        }

        return $sections;
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
