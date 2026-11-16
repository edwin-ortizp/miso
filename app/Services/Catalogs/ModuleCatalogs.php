<?php

namespace App\Services\Catalogs;

use App\Models\ModuleSetting;
use App\Models\User;
use App\Support\CategoryCatalog;
use App\Support\DefaultDrinkTypes;
use App\Support\DefaultExerciseTypes;
use App\Support\DefaultMoodStates;
use App\Support\DefaultTaskCategories;
use App\Support\DefaultVehicleExpenseCategories;
use App\Actions\SeedDefaultCircles;
use App\Actions\SeedDefaultMealCategories;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el catálogo inicial de un módulo la primera vez que la cuenta lo usa.
 * Se recuerda en `module_settings` (clave `catalogs`) para que borrar un valor
 * predeterminado no lo traiga de vuelta. Las cuentas anteriores a esta lógica se
 * marcan como sembradas en una migración.
 */
class ModuleCatalogs
{
    public const KEY = 'catalogs';

    /** Módulos con catálogo inicial. */
    public const MODULES = ['tasks', 'water', 'exercise', 'mood', 'negative-habits', 'relationships', 'vehicles', 'meals'];

    public static function ensure(int $userId, string $module): void
    {
        if (! in_array($module, self::MODULES, true) || in_array($module, self::provisioned($userId), true)) {
            return;
        }
        $user = User::withoutGlobalScopes()->find($userId);
        if (! $user) {
            return;
        }

        DB::transaction(function () use ($user, $userId, $module) {
            self::seed($user, $module);
            $row = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => self::KEY]);
            $row->user_id = $userId;
            $row->settings = ['modules' => array_values(array_unique([...($row->settings['modules'] ?? []), $module]))];
            $row->save();
        });
        self::forget($userId);
    }

    public static function ensureAll(int $userId): void
    {
        foreach (self::MODULES as $module) {
            self::ensure($userId, $module);
        }
    }

    /** @return list<string> */
    public static function provisioned(int $userId): array
    {
        // Caché de la petición: el almacén «array» vive y muere con la aplicación.
        return Cache::store('array')->rememberForever(self::cacheKey($userId), fn () => ModuleSetting::withoutGlobalScopes()
            ->where('user_id', $userId)->where('module', self::KEY)->first()?->settings['modules'] ?? []);
    }

    private static function seed(User $user, string $module): void
    {
        match ($module) {
            'tasks' => DefaultTaskCategories::createFor($user),
            'water' => [DefaultDrinkTypes::createFor($user), CategoryCatalog::seedDomain($user->id, 'drinks')],
            'exercise' => [DefaultExerciseTypes::createFor($user), CategoryCatalog::seedDomain($user->id, 'exercise')],
            'mood' => [DefaultMoodStates::createFor($user), CategoryCatalog::seedDomain($user->id, 'mood')],
            'negative-habits' => CategoryCatalog::seedDomain($user->id, 'negative-habits'),
            'relationships' => [SeedDefaultCircles::for($user), CategoryCatalog::seedDomain($user->id, 'contacts'), CategoryCatalog::seedDomain($user->id, 'relationship-events')],
            'vehicles' => [DefaultVehicleExpenseCategories::createFor($user), CategoryCatalog::seedDomain($user->id, 'maintenance')],
            'meals' => SeedDefaultMealCategories::for($user),
        };
    }

    public static function forget(int $userId): void
    {
        Cache::store('array')->forget(self::cacheKey($userId));
    }

    private static function cacheKey(int $userId): string
    {
        return 'module-catalogs.'.$userId;
    }
}
