<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Game\Guardian\GuardianAdvisor;
use App\Services\Game\Guardian\ModuleProgress;
use App\Services\Game\ModuleGuardian;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Aviso del guardián (CHG-068): en cada franja activa, si queda algo pendiente
 * en un módulo con meta diaria, crea UNA notificación `guardian_nudge` en la
 * tabla `notifications`. El índice único (usuario, origen, tipo, ocurrencia)
 * con ocurrencia `{fecha}:{franja}` hace que correrlo dos veces no duplique.
 */
class GuardianNudgePlanner
{
    public const TYPE = 'guardian_nudge';

    public const SOURCE = 'guardian';

    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly ModuleProgress $progress,
        private readonly ModuleGuardian $guardians,
        private readonly GuardianAdvisor $advisor,
    ) {}

    /** Franja vigente en la zona de las notificaciones, o null fuera de franja. */
    public static function slotAt(CarbonInterface $now): ?string
    {
        $hour = (int) CarbonImmutable::instance($now)->setTimezone(NotificationSource::TIMEZONE)->format('G');
        foreach (config('game.nudges.slots') as $key => $slot) {
            if ($hour >= $slot['from'] && $hour < $slot['to']) {
                return $key;
            }
        }

        return null;
    }

    /** Origen determinista por módulo: la columna `source_id` no admite nulos. */
    public static function sourceId(string $module): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'miso:guardian:'.$module)->toString();
    }

    public static function moduleFor(string $sourceId): ?string
    {
        foreach (config('game.nudges.modules') as $module) {
            if (self::sourceId($module) === $sourceId) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return array{status: string, module?: string, title?: string, body?: string}
     *                                                                          status: created|exists|off|no-slot|no-device|cap|nothing-pending|dry-run
     */
    public function run(User $user, CarbonInterface $now, bool $dryRun = false): array
    {
        if (! config('game.nudges.enabled')) {
            return ['status' => 'off'];
        }
        $prefs = $this->preferences->get($user->id);
        if (! $prefs['guardian_nudges']) {
            return ['status' => 'off'];
        }
        $slot = self::slotAt($now);
        if ($slot === null || ! ($prefs['nudge_'.$slot] ?? false)) {
            return ['status' => 'no-slot'];
        }
        if (! PushSubscription::where('user_id', $user->id)->exists()) {
            return ['status' => 'no-device'];
        }

        $date = CarbonImmutable::instance($now)->setTimezone(NotificationSource::TIMEZONE)->toDateString();
        $base = UserNotification::withoutGlobalScopes()->where('user_id', $user->id)->where('type', self::TYPE);
        if ((clone $base)->where('occurrence_key', $date.':'.$slot)->exists()) {
            return ['status' => 'exists'];
        }
        if ((clone $base)->where('occurrence_key', 'like', $date.':%')->count() >= (int) config('game.nudges.daily_cap')) {
            return ['status' => 'cap'];
        }

        $message = $this->asUser($user, fn () => $this->message($user, $now));
        if ($message === null) {
            return ['status' => 'nothing-pending'];
        }
        if ($dryRun) {
            return ['status' => 'dry-run'] + $message;
        }

        $notice = UserNotification::withoutGlobalScopes()->firstOrCreate([
            'user_id' => $user->id, 'source_type' => self::SOURCE, 'source_id' => self::sourceId($message['module']),
            'type' => self::TYPE, 'occurrence_key' => $date.':'.$slot,
        ], ['title' => $message['title'], 'body' => $message['body'], 'scheduled_for' => CarbonImmutable::instance($now)->utc()]);

        return ['status' => $notice->wasRecentlyCreated ? 'created' : 'exists'] + $message;
    }

    /** @return array{module: string, title: string, body: string}|null módulo con más pendiente */
    private function message(User $user, CarbonInterface $now): ?array
    {
        $best = null;
        foreach (config('game.nudges.modules') as $module) {
            $p = $this->progress->for($module);
            if ($p['total'] <= 0 || $p['done'] >= $p['total']) {
                continue;
            }
            $missing = 1 - $p['done'] / $p['total'];
            if ($best === null || $missing > $best['missing']) {
                $best = ['module' => $module, 'missing' => $missing, 'progress' => $p];
            }
        }
        if ($best === null) {
            return null;
        }

        $guardian = $this->guardians->pick($user->id, $best['module']);
        $advice = $this->advisor->advise($best['module'], $now, null, $best['progress']);
        $body = trim(html_entity_decode(strip_tags($advice['html']), ENT_QUOTES | ENT_HTML5));

        return [
            'module' => $best['module'],
            'title' => $guardian ? $guardian['name'].' · MiSo' : 'MiSo',
            'body' => Str::limit($body !== '' ? $body : ModuleGuardian::line($best['module'], $best['progress']['done'], $best['progress']['total'], false, $now), 120),
        ];
    }

    /** Los proveedores de avance y consejo usan los scopes del usuario autenticado. */
    private function asUser(User $user, callable $fn): mixed
    {
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            return $fn();
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }
}
