<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Un día en que el usuario usó MiSo (CHG-067). `first_channel` es por dónde
 * entró primero ese día; `records_count` cuenta las actividades del juego.
 */
class UserActiveDay extends Model
{
    public const CHANNELS = ['direct', 'push', 'checkin', 'close', 'shortcut', 'return'];

    protected $fillable = ['user_id', 'date', 'first_channel', 'records_count', 'closed_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'records_count' => 'integer', 'closed_at' => 'datetime'];
    }

    /** CHG-070: marca el día como cerrado ("Listo, terminé el día"). Idempotente: conserva el primer cierre. */
    public static function closeDay(int $userId, string $date): void
    {
        self::markActive($userId, 'close', $date);
        DB::table('user_active_days')->where('user_id', $userId)->where('date', $date)->whereNull('closed_at')
            ->update(['closed_at' => now(), 'updated_at' => now()]);
    }

    public static function isClosed(int $userId, string $date): bool
    {
        return DB::table('user_active_days')->where('user_id', $userId)->where('date', $date)->whereNotNull('closed_at')->exists();
    }

    public static function channel(?string $via): string
    {
        return in_array($via, self::CHANNELS, true) ? $via : 'direct';
    }

    /** Marca el día como activo; el primer canal del día no se sobrescribe. */
    public static function markActive(int $userId, string $channel, ?string $date = null): void
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
        self::markActive($userId, 'direct', $date);
        DB::table('user_active_days')->where('user_id', $userId)->where('date', $date)
            ->update(['records_count' => DB::raw('records_count + 1'), 'updated_at' => now()]);
    }
}
