<?php

use App\Support\DefaultMaintenanceTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CHG-121: las plantillas globales de mantenimiento ya no dependen de un seeder, y sus categorías
 * pasan a claves sin tildes con nombre visible legible. Las claves con tilde se fusionan en su
 * equivalente sin tilde; los registros conservan su categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_templates')) {
            return;
        }

        foreach (DefaultMaintenanceTemplates::LEGACY_KEYS as $old => $new) {
            DB::table('maintenance_templates')->where('category', $old)->update(['category' => $new]);
        }
        DefaultMaintenanceTemplates::sync();

        if (! Schema::hasTable('user_categories')) {
            return;
        }

        foreach (DefaultMaintenanceTemplates::LEGACY_KEYS as $old => $new) {
            $rows = DB::table('user_categories')->where('domain', 'maintenance')->where('key', $old)->get();
            foreach ($rows as $row) {
                $canonical = DB::table('user_categories')->where('user_id', $row->user_id)->where('domain', 'maintenance')->where('key', $new)->first();
                if ($canonical) {
                    DB::table('user_categories')->where('parent_id', $row->id)->update(['parent_id' => $canonical->id]);
                    DB::table('user_categories')->where('id', $row->id)->delete();
                } else {
                    DB::table('user_categories')->where('id', $row->id)->update(['key' => $new]);
                }
            }
        }

        // Las filas sembradas con el nombre igual a la clave cruda pasan a mostrar su etiqueta.
        foreach (DefaultMaintenanceTemplates::CATEGORIES as $key => $label) {
            $rows = DB::table('user_categories')->where('domain', 'maintenance')->where('key', $key)
                ->whereIn('name', [$key, array_search($key, DefaultMaintenanceTemplates::LEGACY_KEYS, true) ?: $key])->get();
            foreach ($rows as $row) {
                $taken = DB::table('user_categories')->where('user_id', $row->user_id)->where('domain', 'maintenance')
                    ->where('normalized_name', mb_strtolower($label))->where('id', '!=', $row->id)->exists();
                if (! $taken) {
                    DB::table('user_categories')->where('id', $row->id)
                        ->update(['name' => $label, 'normalized_name' => mb_strtolower($label)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Normalización de datos: no se revierte; las plantillas globales se conservan.
    }
};
