<?php

namespace App\Livewire\Concerns;

use App\Services\Catalog\CatalogPicker;

/** Marcar y desmarcar favoritos personales de un catálogo (CHG-150). */
trait WithCatalogFavorites
{
    public function toggleFavorite(string $domain, string $id): void
    {
        abort_unless(CatalogPicker::supports($domain), 404);

        app(CatalogPicker::class)->toggle($domain, $id);
    }

    /** Orden SQL «favoritos primero» para una consulta del catálogo. */
    protected function favoritesFirst($query, string $domain)
    {
        $ids = app(CatalogPicker::class)->favoriteIds($domain);

        if ($ids === []) {
            return $query;
        }

        $positions = collect($ids)->map(fn ($id, $index) => 'WHEN ? THEN '.(int) $index)->implode(' ');

        return $query->orderByRaw("CASE id {$positions} ELSE ".count($ids).' END', $ids);
    }
}
