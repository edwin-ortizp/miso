<?php

namespace App\Models\Traits;

use App\Models\UserCategory;
use Illuminate\Support\Facades\Schema;

trait ResolvesCategoryCatalog
{
    public static function bootResolvesCategoryCatalog(): void
    {
        static::saving(function ($record) {
            // Global maintenance templates deliberately have no owner.
            if (! $record instanceof \App\Models\MaintenanceTemplate) {
                $record->user_id ??= auth()->id();
            }
            // Imports and existing integrations can still supply stable legacy keys.
            // La comprobación protege a las migraciones antiguas que guardan estos modelos antes
            // de que exista el catálogo. CHG-095: una vez vista la tabla no se vuelve a preguntar
            // al esquema en cada guardado (solo se recuerda el «sí», que ya no cambia).
            static $catalogReady = false;
            if ($record->user_id && filled($record->category) && ($catalogReady = $catalogReady || Schema::hasTable('user_categories'))) {
                $domain = $record->categoryDomain();
                $catalog = UserCategory::withoutGlobalScopes()->where('user_id', $record->user_id)->where('domain', $domain);
                if (! (clone $catalog)->where('key', $record->category)->exists()) {
                    $known = (clone $catalog)->where('normalized_name', mb_strtolower(trim($record->category)))->first();
                    if ($known) {
                        $record->category = $known->key;
                        return;
                    }
                }
                UserCategory::withoutGlobalScopes()->firstOrCreate(
                    ['user_id' => $record->user_id, 'domain' => $domain, 'key' => $record->category],
                    ['name' => \App\Support\CategoryCatalog::defaults($domain)[$record->category] ?? $record->category],
                );
            }
        });
    }

    public function categoryDomain(): string
    {
        foreach (\App\Support\CategoryCatalog::DOMAINS as $domain => [$table]) {
            if ($table === $this->getTable()) {
                return $domain;
            }
        }
        throw new \LogicException('Unknown category domain');
    }

    public function getCategoryLabelAttribute(): string
    {
        $owner = $this->user_id ?: auth()->id();
        return $owner ? (\App\Support\CategoryCatalog::options($this->categoryDomain(), $owner)[$this->category] ?? $this->category ?? 'Sin categoría') : ($this->category ?? 'Sin categoría');
    }
}
