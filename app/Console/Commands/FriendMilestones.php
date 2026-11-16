<?php

namespace App\Console\Commands;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\FriendNotices;
use App\Services\Social\SharedStreak;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * CHG-124: avisa los hitos de racha compartida (7, 30, 100 días) aunque nadie abra /amigos.
 * Idempotente: el aviso es único por (usuario, par, hito) en el índice de `notifications`.
 */
class FriendMilestones extends Command
{
    private const CURSOR = 'miso:friend-milestones:cursor';

    protected $signature = 'miso:friend-milestones {--dry-run : Cuenta los pares en un hito sin crear avisos}';

    protected $description = 'Crea los avisos de hitos de racha compartida entre amigos (una vez por par y hito)';

    public function handle(SharedStreak $shared, FriendNotices $notices): int
    {
        $pairs = 0;
        $reached = 0;
        // Hosting compartido: cada corrida se detiene a los 40 s y la siguiente sigue desde el cursor.
        $deadline = microtime(true) + 40;
        $dry = (bool) $this->option('dry-run');
        $cursor = $dry ? 0 : (int) Cache::get(self::CURSOR, 0);
        $finished = true;
        Friendship::query()->where('status', Friendship::ACCEPTED)->where('id', '>', $cursor)->chunkById(200, function ($friendships) use ($shared, $notices, $deadline, &$pairs, &$reached, &$finished, &$cursor) {
            $users = User::whereIn('id', $friendships->pluck('requester_id'))->get()->keyBy('id');
            foreach ($friendships as $friendship) {
                if (microtime(true) > $deadline) {
                    $finished = false;

                    return false;
                }
                $cursor = $friendship->id;
                $pairs++;
                $me = $users[$friendship->requester_id] ?? null;
                $streak = $me ? $shared->for($me, $friendship->addressee_id) : null;
                $milestone = $streak ? $shared->milestoneReached($streak['days']) : null;
                if (! $milestone) {
                    continue;
                }
                $reached++;
                if (! $this->option('dry-run')) {
                    $notices->sharedStreak($me, $friendship->addressee_id, $milestone);
                }
            }
        });

        if (! $dry) {
            $finished ? Cache::forget(self::CURSOR) : Cache::forever(self::CURSOR, $cursor);
            Cache::forever('miso:last-run:friend-milestones', now()->toIso8601String());
        }
        $this->info("Amistades revisadas: {$pairs}; en un hito: {$reached}".($this->option('dry-run') ? ' (sin escribir)' : ''));

        return self::SUCCESS;
    }
}
