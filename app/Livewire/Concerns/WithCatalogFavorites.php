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
        return app(CatalogPicker::class)->orderFavoritesFirst($query, $domain);
    }
}
