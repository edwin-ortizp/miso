<?php

namespace App\Services\Catalog;

use App\Models\CatalogFavorite;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Favoritos (los marca la persona) y más usados (los calcula el sistema) de un catálogo personal.
 * Son conceptos distintos: los más usados solo se sugieren, nunca se vuelven favoritos solos.
 */
class CatalogPicker
{
    public function __construct(private RequestMemo $memo)
    {
    }

    public static function supports(string $domain): bool
    {
        return array_key_exists($domain, config('catalog_picker.domains', []));
    }

    /** @return array{model: class-string, logs: string, foreign_key: string, noun: string} */
    private function definition(string $domain): array
    {
        abort_unless(self::supports($domain), 404, 'Catálogo sin favoritos: '.$domain);

        return config("catalog_picker.domains.{$domain}");
    }

    private function userId(): ?int
    {
        return auth()->id();
    }

    /** Identificadores favoritos, en el orden en que la persona los fue marcando. @return list<string> */
    public function favoriteIds(string $domain): array
    {
        $userId = $this->userId();

        if (! $userId) {
            return [];
        }

        return $this->memo->remember("catalog-favorites:{$userId}:{$domain}", fn () => CatalogFavorite::query()
            ->where('catalog_domain', $domain)->orderBy('position')->orderBy('created_at')->pluck('item_id')->all());
    }

    public function isFavorite(string $domain, string $id): bool
    {
        return in_array($id, $this->favoriteIds($domain), true);
    }

    /** Marca o desmarca. Devuelve el estado final; un elemento ajeno no se puede marcar. */
    public function toggle(string $domain, string $id): bool
    {
        return $this->set($domain, $id, ! $this->isFavorite($domain, $id));
    }

    public function set(string $domain, string $id, bool $favorite): bool
    {
        $model = $this->definition($domain)['model'];
        $userId = $this->userId();

        if (! $userId || ! $model::query()->whereKey($id)->exists()) {
            return false;
        }

        if ($favorite) {
            CatalogFavorite::firstOrCreate(
                ['catalog_domain' => $domain, 'item_id' => $id],
                ['position' => (int) CatalogFavorite::where('catalog_domain', $domain)->max('position') + 1],
            );
        } else {
            CatalogFavorite::where('catalog_domain', $domain)->where('item_id', $id)->delete();
        }

        $this->memo->flush();

        return $favorite;
    }

    /** Se llama al borrar un elemento del catálogo. */
    public function forget(string $domain, string $id): void
    {
        CatalogFavorite::withoutGlobalScopes()->where('catalog_domain', $domain)->where('item_id', $id)->delete();
        $this->memo->flush();
    }

    /**
     * El catálogo completo con los favoritos primero (en su orden) y el resto por nombre.
     *
     * @param  Collection|null  $items  Colección ya cargada del catálogo; si no, se carga completa.
     */
    public function ordered(string $domain, ?Collection $items = null): Collection
    {
        $model = $this->definition($domain)['model'];
        $items ??= $model::query()->orderBy('name')->get();
        $order = array_flip($this->favoriteIds($domain));

        return $items->sortBy(fn ($item) => [isset($order[$item->id]) ? $order[$item->id] : PHP_INT_MAX, mb_strtolower((string) $item->name)])->values();
    }

    /** Solo los favoritos, en su orden. */
    public function favorites(string $domain, ?Collection $items = null): Collection
    {
        $ids = array_flip($this->favoriteIds($domain));

        return $this->ordered($domain, $items)->filter(fn ($item) => isset($ids[$item->id]))->values();
    }

    /**
     * Más usados por la persona en los últimos días: [id => registros], de mayor a menor.
     *
     * @return array<string, int>
     */
    public function mostUsed(string $domain, int $limit = 5): array
    {
        $userId = $this->userId();
        $definition = $this->definition($domain);

        if (! $userId) {
            return [];
        }

        $days = (int) config('catalog_picker.usage_days', 60);
        $minimum = (int) config('catalog_picker.usage_minimum', 2);

        return $this->memo->remember("catalog-most-used:{$userId}:{$domain}:{$limit}", fn () => DB::table($definition['logs'])
            ->where('user_id', $userId)
            ->whereNotNull($definition['foreign_key'])
            ->where('date', '>=', now(config('app.timezone'))->subDays($days)->toDateString())
            ->selectRaw($definition['foreign_key'].' as item_id, count(*) as uses')
            ->groupBy($definition['foreign_key'])
            ->havingRaw('count(*) >= ?', [$minimum])
            ->orderByDesc('uses')->limit($limit + count($this->favoriteIds($domain)))
            ->pluck('uses', 'item_id')->map(fn ($uses) => (int) $uses)->all());
    }

    /** Más usados que todavía no son favoritos: solo sugerencias. @return Collection<int, \Illuminate\Database\Eloquent\Model> */
    public function suggestions(string $domain, int $limit = 5): Collection
    {
        $favorites = array_flip($this->favoriteIds($domain));
        $ids = array_slice(array_keys(array_diff_key($this->mostUsed($domain, $limit), $favorites)), 0, $limit);

        if ($ids === []) {
            return collect();
        }

        $items = $this->definition($domain)['model']::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $items->get($id))->filter()->values();
    }

    /**
     * Elementos para registrar rápido (chips): favoritos primero y, si faltan, los más usados.
     */
    public function quick(string $domain, int $limit, ?Collection $items = null): Collection
    {
        $ordered = $this->ordered($domain, $items);
        $favorites = array_flip($this->favoriteIds($domain));
        $picked = $ordered->filter(fn ($item) => isset($favorites[$item->id]))->take($limit)->values();

        if ($picked->count() < $limit) {
            $used = $this->mostUsed($domain, $limit);
            $rest = $ordered->reject(fn ($item) => isset($favorites[$item->id]))
                ->sortBy(fn ($item) => isset($used[$item->id]) ? -$used[$item->id] : 0)->values();
            $picked = $picked->concat($rest->take($limit - $picked->count()))->values();
        }

        return $picked;
    }

    /**
     * Bebida para «+1 vaso»: entre los favoritos, la que se llame agua o la primera; sin favoritos,
     * la heurística de siempre (una que se llame agua o la primera por nombre).
     */
    public function defaultWater(): ?\App\Models\DrinkType
    {
        $favorites = $this->favorites('drinks');
        $water = fn ($items) => $items->first(fn ($type) => str_contains(mb_strtolower($type->name), 'agua'));

        return $water($favorites) ?? $favorites->first()
            ?? \App\Models\DrinkType::where('name', 'like', '%agua%')->orderBy('name')->first()
            ?? \App\Models\DrinkType::orderBy('name')->first();
    }
}
