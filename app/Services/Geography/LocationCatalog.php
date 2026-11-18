<?php

namespace App\Services\Geography;

use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationCatalog
{
    public static function normalize(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }

    public static function cityOptions(string $country, string $search, string $selected): array
    {
        if ($country === '') {
            return [];
        }

        $cities = City::query()->where('country_code', $country);
        $term = trim(str_replace(['%', '_'], ' ', self::normalize($search)));
        $options = (clone $cities)
            ->when($term !== '', fn ($query) => $query->where('normalized_name', 'like', addcslashes($term, '\\%_').'%'))
            ->orderByDesc('population')->orderBy('id')->limit(25)->get();
        if (ctype_digit($selected) && ! $options->contains('id', (int) $selected)) {
            $chosen = $cities->find($selected);
            if ($chosen) {
                $options->prepend($chosen);
            }
        }

        return $options->mapWithKeys(fn (City $city) => [$city->id => $city->label()])->all();
    }

    /** Resolve catalog values, including the original city input of the MiSo tools. */
    public static function attributes(array $input): array
    {
        $country = strtoupper(trim($input['country_code'] ?? '')) ?: null;
        $cityId = $input['city_id'] ?? null;
        $name = trim($input['city'] ?? '');
        if ($country && ! Country::query()->whereKey($country)->exists()) {
            throw ValidationException::withMessages(['country_code' => 'Selecciona un país del catálogo.']);
        }

        $city = null;
        if (filled($cityId)) {
            $city = $country ? City::query()->where('country_code', $country)->find($cityId) : null;
            if (! $city) {
                throw ValidationException::withMessages(['city_id' => 'Selecciona una ciudad del país elegido.']);
            }
        } elseif ($name !== '') {
            $matches = City::query()
                ->when($country, fn ($query) => $query->where('country_code', $country))
                ->where('normalized_name', self::normalize($name))->limit(2)->get();
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['city' => 'Indica el código del país y el identificador de la ciudad del catálogo, o deja la ciudad vacía.']);
            }
            $city = $matches->first();
        }

        return ['country_code' => $country ?? $city?->country_code, 'city_id' => $city?->id, 'city' => $city?->name];
    }
}
