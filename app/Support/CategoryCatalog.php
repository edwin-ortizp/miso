<?php

namespace App\Support;

use App\Models\UserCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Stable legacy keys remain on entries; names and nesting live in personal catalogs. */
class CategoryCatalog
{
    public const DOMAINS = [
        'learning' => ['learning_formations', 'Formación', 'learning'],
        'drinks' => ['drink_types', 'Bebidas', 'water'],
        'exercise' => ['exercise_types', 'Ejercicios', 'exercise'],
        'mood' => ['mood_states', 'Estados de ánimo', 'mood'],
        'negative-habits' => ['negative_habit_definitions', 'Hábitos a evitar', 'negative-habits'],
        'contacts' => ['relationships', 'Contactos', 'relationships'],
        'relationship-events' => ['relationship_events', 'Eventos de relaciones', 'relationships'],
        'maintenance' => ['maintenance_templates', 'Plantillas de mantenimiento', 'vehicles'],
    ];

    public static function defaults(string $domain): array
    {
        return match ($domain) {
            'drinks' => ['water' => 'Agua', 'coffee' => 'Café', 'tea' => 'Té', 'juice' => 'Jugo', 'soda' => 'Gaseosa', 'milk' => 'Leche', 'sports' => 'Bebida deportiva', 'beer' => 'Cerveza', 'wine' => 'Vino', 'smoothie' => 'Batido'],
            'exercise' => DefaultExerciseTypes::CATEGORIES,
            'mood' => array_combine(DefaultMoodStates::categories(), DefaultMoodStates::categories()),
            'contacts' => ['familia' => 'Familia', 'amigo' => 'Amigo', 'trabajo' => 'Trabajo', 'pareja' => 'Pareja', 'otro' => 'Otro'],
            'relationship-events' => \App\Models\RelationshipEvent::CATEGORIES,
            'negative-habits' => ['health' => 'Salud', 'productivity' => 'Productividad', 'social' => 'Social', 'emotional' => 'Emocional', 'finance' => 'Finanzas', 'digital' => 'Digital', 'environment' => 'Medio ambiente'],
            'learning' => ['technology' => 'Tecnología', 'languages' => 'Idiomas', 'science' => 'Ciencias', 'humanities' => 'Humanidades', 'health' => 'Salud', 'business' => 'Negocios', 'arts' => 'Arte', 'other' => 'Otro'],
            default => [],
        };
    }

    public static function seedFor(int $userId): void
    {
        if (! Schema::hasTable('user_categories')) {
            return;
        }
        foreach (self::DOMAINS as $domain => [$table]) {
            $values = self::defaults($domain);
            if (Schema::hasTable($table)) {
                $query = DB::table($table)->where(fn ($q) => $q->where('user_id', $userId)
                    ->when($domain === 'maintenance', fn ($q) => $q->orWhereNull('user_id')));
                foreach ($query->whereNotNull('category')->distinct()->pluck('category') as $key) {
                    if ($key !== '') {
                        $values[$key] ??= $key;
                    }
                }
            }
            foreach ($values as $key => $name) {
                if (DB::table('user_categories')->where('user_id', $userId)->where('domain', $domain)->where('key', (string) $key)->exists()) {
                    continue;
                }
                $base = $name;
                $suffix = 2;
                while (DB::table('user_categories')->where('user_id', $userId)->where('domain', $domain)->where('normalized_name', mb_strtolower($name))->exists()) {
                    $name = $base.' ('.$suffix++.')';
                }
                DB::table('user_categories')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'user_id' => $userId, 'domain' => $domain,
                    'key' => (string) $key, 'name' => $name, 'normalized_name' => mb_strtolower($name),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        // Estas inserciones no pasan por Eloquent: se olvida a mano lo memorizado en la petición.
        app(RequestMemo::class)->flush();
    }

    public static function options(string $domain, ?int $userId = null): array
    {
        $userId ??= auth()->id();
        if (! $userId) {
            return [];
        }
        // CHG-135: una pantalla pide el mismo catálogo por cada fila y selector; se lee una vez por petición.
        // AppServiceProvider vacía la memoria al guardar o borrar una UserCategory.
        return app(RequestMemo::class)->remember("category-catalog:options:{$userId}:{$domain}",
            fn () => UserCategory::hierarchyOptions($userId, 'key', $domain));
    }

    public static function expand(string $domain, array $keys, ?int $userId = null): array
    {
        $userId ??= auth()->id();
        return $userId ? UserCategory::expandSelection($keys, $userId, 'key', $domain) : [];
    }

    public static function inUse(UserCategory $category): bool
    {
        $table = self::DOMAINS[$category->domain][0];
        return DB::table($table)->where('category', $category->key)
            ->where(fn ($q) => $q->where('user_id', $category->user_id)
                ->when($category->domain === 'maintenance', fn ($q) => $q->orWhereNull('user_id')))->exists();
    }
}
