<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CHG-121: los catálogos iniciales ahora se siembran al abrir cada módulo. Las cuentas que ya
 * existen los recibieron al registrarse: se marcan como sembrados para que no se les restauren
 * valores predeterminados que borraron.
 */
return new class extends Migration
{
    private const MODULES = ['tasks', 'water', 'exercise', 'mood', 'negative-habits', 'relationships', 'vehicles', 'meals'];

    public function up(): void
    {
        DB::table('users')->orderBy('id')->each(function ($user) {
            $exists = DB::table('module_settings')->where('user_id', $user->id)->where('module', 'catalogs')->exists();
            if ($exists) {
                return;
            }
            DB::table('module_settings')->insert([
                'user_id' => $user->id, 'module' => 'catalogs',
                'settings' => json_encode(['modules' => self::MODULES]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('module_settings')->where('module', 'catalogs')->delete();
    }
};
