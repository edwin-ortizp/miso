<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** CHG-153: los favoritos de recetas pasan de la columna `recipes.favorite` a `catalog_favorites` (la columna se conserva sin uso). */
return new class extends Migration
{
    public function up(): void
    {
        $position = [];

        DB::table('recipes')->where('favorite', true)->orderBy('created_at')->orderBy('id')->each(function ($recipe) use (&$position) {
            $position[$recipe->user_id] = ($position[$recipe->user_id] ?? 0) + 1;

            DB::table('catalog_favorites')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'user_id' => $recipe->user_id, 'catalog_domain' => 'recipes',
                'item_id' => $recipe->id, 'position' => $position[$recipe->user_id], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('catalog_favorites')->where('catalog_domain', 'recipes')->orderBy('id')->each(function ($favorite) {
            DB::table('recipes')->where('id', $favorite->item_id)->update(['favorite' => true]);
        });
        DB::table('catalog_favorites')->where('catalog_domain', 'recipes')->delete();
    }
};
