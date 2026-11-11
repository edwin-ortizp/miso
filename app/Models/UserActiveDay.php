<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Un día en que el usuario usó MiSo (CHG-066). `first_channel` es por dónde
 * entró primero ese día; `records_count` cuenta las actividades del juego.
 */
class UserActiveDay extends Model
{
    public const CHANNELS = ['direct', 'push', 'checkin', 'close'];

    protected $fillable = ['user_id', 'date', 'first_channel', 'records_count'];

    protected function casts(): array
    {
        return ['date' => 'date', 'records_count' => 'integer'];
    }

    public static function channel(?string $via): string
    {
        return in_array($via, self::CHANNELS, true) ? $via : 'direct';
    }

    /** Marca el día como activo; el primer canal del día no se sobrescribe. */
    public static function touch(int $userId, string $channel, ?string $date = null): void
    {
        $date ??= now()->toDateString();
        DB::table('user_active_days')->insertOrIgnore([
            'user_id' => $userId, 'date' => $date, 'first_channel' => self::channel($channel),
            'records_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Suma un registro al día (también si llegó por MCP sin abrir la app). */
    public static function countRecord(int $userId, ?string $date = null): void
    {
        $date ??= now()->toDateString();
        self::touch($userId, 'direct', $date);
        DB::table('user_active_days')->where('user_id', $userId)->where('date', $date)
            ->update(['records_count' => DB::raw('records_count + 1'), 'updated_at' => now()]);
    }
}
