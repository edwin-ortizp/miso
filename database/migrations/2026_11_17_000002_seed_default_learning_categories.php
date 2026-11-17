<?php

use App\Support\CategoryCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CHG-147: las categorías de Formación ahora traen valores por defecto; se siembran para los usuarios existentes. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->orderBy('id')->each(fn ($user) => CategoryCatalog::seedFor($user->id));
    }

    public function down(): void
    {
        // Las categorías sembradas son datos del usuario y no se retiran al revertir.
    }
};
