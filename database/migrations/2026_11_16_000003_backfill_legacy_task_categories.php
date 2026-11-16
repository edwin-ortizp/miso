<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CHG-121: los nombres de las categorías de trabajo históricas dejan de estar en el código.
 * Quien tiene tareas con una de esas claves y ninguna categoría con esa clave recibe la categoría
 * con su nombre de siempre, para que la etiqueta no cambie.
 */
return new class extends Migration
{
    private const LEGACY = [
        'siigo' => 'Siigo', 'entreagiles' => 'EntreAgiles', 'gesthor' => 'Gesthor', 'certmind' => 'CertMind', 'unicauca' => 'Unicauca',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('task_categories') || ! Schema::hasTable('tasks')) {
            return;
        }

        $rows = DB::table('tasks')->whereIn('category', array_keys(self::LEGACY))
            ->select('user_id', 'category')->distinct()->get();

        foreach ($rows as $row) {
            if (DB::table('task_categories')->where('user_id', $row->user_id)->where('key', $row->category)->exists()) {
                continue;
            }
            $name = self::LEGACY[$row->category];
            if (DB::table('task_categories')->where('user_id', $row->user_id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                continue;
            }
            DB::table('task_categories')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $row->user_id, 'key' => $row->category, 'name' => $name,
                'icon' => 'bi-tag', 'sort_order' => (int) DB::table('task_categories')->where('user_id', $row->user_id)->max('sort_order') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Datos de usuario: no se revierte.
    }
};
