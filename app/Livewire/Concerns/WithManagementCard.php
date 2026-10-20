<?php

namespace App\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Estado compartido de la Card de gestión (x-ui.management-card):
 * paginación del servidor con filas por página 10/25/50/100 (25 por defecto)
 * y regreso a la primera página cuando cambian la búsqueda o las filas.
 */
trait WithManagementCard
{
    use WithPagination;

    #[Url(as: 'per_page', history: true, except: 25)]
    public int $perPage = 25;

    /**
     * @return list<int>
     */
    public static function perPageOptions(): array
    {
        return [10, 25, 50, 100];
    }

    public function updatedPerPage(mixed $value): void
    {
        if (! in_array((int) $value, static::perPageOptions(), true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    protected function perPage(): int
    {
        return in_array($this->perPage, static::perPageOptions(), true) ? $this->perPage : 25;
    }

    /**
     * Chip de la franja «Filtros aplicados» (x-ui.applied-filters): por defecto `key` es la propiedad
     * del filtro; se restablece a su valor inicial o, si es una lista, se quita `value`.
     * Los componentes con filtros compuestos la redefinen.
     */
    public function removeFilter(string $key, ?string $value = null): void
    {
        if (! property_exists($this, $key)) {
            return;
        }

        if (is_array($this->{$key}) && $value !== null) {
            $this->{$key} = array_values(array_diff($this->{$key}, [$value]));
        } else {
            $this->reset($key);
        }

        $this->resetPage();
    }

    /**
     * «Limpiar filtros» de la franja: restablece las propiedades de `filterProperties()`.
     */
    public function clearFilters(): void
    {
        $this->reset($this->filterProperties());
        $this->resetPage();
    }

    /**
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return [];
    }

    /**
     * Pagina una colección ya ordenada o calculada en PHP (cumpleaños, planes ordenados por visitas…).
     */
    protected function paginateCollection(Collection $items, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = $this->perPage();
        $page = max(1, min((int) $this->getPage($pageName), (int) max(1, ceil($items->count() / $perPage))));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );
    }
}
