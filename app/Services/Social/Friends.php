<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/** Solicitudes y amistades entre cuentas (CHG-122). Respuestas uniformes: no revelan qué cuentas existen. */
class Friends
{
    public const LIMIT = 100;
    public const OK = 'ok';
    public const LIMIT_REACHED = 'limit';
    public const THROTTLED = 'throttled';
    public const INVALID = 'invalid';

    /** Envía una solicitud con un código o con «@usuario». Misma respuesta exista o no la cuenta. */
    public function request(User $me, string $input, ?string $ip = null): string
    {
        $input = trim($input);
        if ($input === '') {
            return self::INVALID;
        }
        $keys = ["friend-lookup:user:{$me->id}", 'friend-lookup:ip:'.($ip ?? 'cli')];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return self::THROTTLED;
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }
        if ($this->count($me->id) >= self::LIMIT) {
            return self::LIMIT_REACHED;
        }

        $target = $this->find($input);
        if ($target && $target->id !== $me->id) {
            $this->open($me, $target);
        }

        return self::OK;
    }

    public function accept(User $me, int $friendshipId): bool
    {
        $friendship = Friendship::forUser($me->id)->whereKey($friendshipId)->first();
        if (! $friendship || $friendship->status !== Friendship::PENDING || $friendship->addressee_id !== $me->id) {
            return false;
        }
        if ($this->count($me->id, accepted: true) >= self::LIMIT) {
            return false;
        }
        $friendship->update(['status' => Friendship::ACCEPTED, 'responded_at' => now()]);
        app(FriendNotices::class)->accepted($friendship, $me->id);

        return true;
    }

    /** Rechaza una solicitud recibida o cancela una enviada. */
    public function decline(User $me, int $friendshipId): void
    {
        Friendship::forUser($me->id)->whereKey($friendshipId)->where('status', Friendship::PENDING)->delete();
    }

    public function remove(User $me, int $friendshipId): void
    {
        Friendship::forUser($me->id)->whereKey($friendshipId)->where('status', Friendship::ACCEPTED)->delete();
    }

    public function block(User $me, int $friendshipId): void
    {
        Friendship::forUser($me->id)->whereKey($friendshipId)->where('status', '!=', Friendship::BLOCKED)
            ->get()->each->update(['status' => Friendship::BLOCKED, 'blocked_by' => $me->id, 'responded_at' => now()]);
    }

    public function unblock(User $me, int $friendshipId): void
    {
        Friendship::forUser($me->id)->whereKey($friendshipId)
            ->where('status', Friendship::BLOCKED)->where('blocked_by', $me->id)->delete();
    }

    /** Solicitudes + amistades que cuentan para el límite (las bloqueadas no). */
    public function count(int $userId, bool $accepted = false): int
    {
        return Friendship::forUser($userId)
            ->when($accepted, fn ($q) => $q->where('status', Friendship::ACCEPTED),
                fn ($q) => $q->whereIn('status', [Friendship::PENDING, Friendship::ACCEPTED]))
            ->count();
    }

    /** @return Collection<int, array{id: int, friendship_id: int, username: ?string, avatar_url: ?string, name: string}> */
    public function friends(User $me): Collection
    {
        return $this->others($me, Friendship::ACCEPTED);
    }

    /** Solicitudes recibidas. */
    public function incoming(User $me): Collection
    {
        return $this->others($me, Friendship::PENDING, incoming: true);
    }

    /** Solicitudes enviadas. */
    public function outgoing(User $me): Collection
    {
        return $this->others($me, Friendship::PENDING, incoming: false);
    }

    public function blocked(User $me): Collection
    {
        return $this->others($me, Friendship::BLOCKED)->filter(fn ($row) => $row['blocked_by_me'])->values();
    }

    private function others(User $me, string $status, ?bool $incoming = null): Collection
    {
        $rows = Friendship::forUser($me->id)->where('status', $status)
            ->when($incoming === true, fn ($q) => $q->where('addressee_id', $me->id))
            ->when($incoming === false, fn ($q) => $q->where('requester_id', $me->id))
            ->orderByDesc('id')->get();
        $users = User::whereIn('id', $rows->map(fn ($row) => $row->otherId($me->id)))->get()->keyBy('id');

        return $rows->map(function (Friendship $row) use ($me, $users) {
            $other = $users[$row->otherId($me->id)] ?? null;

            return $other ? [
                'id' => $other->id,
                'friendship_id' => $row->id,
                'username' => $other->username,
                'avatar_url' => $other->avatar_url,
                'name' => $other->full_name ?: $other->name,
                'blocked_by_me' => $row->blocked_by === $me->id,
            ] : null;
        })->filter()->values();
    }

    /** «@usuario» busca por nombre de usuario; cualquier otra cosa se trata como código. */
    private function find(string $input): ?User
    {
        if (str_starts_with($input, '@')) {
            return User::where('username', Username::normalize($input))->first();
        }
        $code = strtoupper(preg_replace('/^MISO-/i', '', preg_replace('/\s+/', '', $input)));

        return User::where('friend_code', $code)->first();
    }

    private function open(User $me, User $target): void
    {
        try {
            DB::transaction(function () use ($me, $target) {
                $existing = Friendship::query()->between($me->id, $target->id)->lockForUpdate()->first();
                if (! $existing) {
                    app(FriendNotices::class)->requested(
                        Friendship::create(['requester_id' => $me->id, 'addressee_id' => $target->id, 'status' => Friendship::PENDING]),
                    );
                } elseif ($existing->status === Friendship::PENDING && $existing->addressee_id === $me->id
                    && $this->count($me->id, accepted: true) < self::LIMIT) {
                    // Solicitud cruzada: ambos quieren; queda aceptada.
                    $existing->update(['status' => Friendship::ACCEPTED, 'responded_at' => now()]);
                    app(FriendNotices::class)->accepted($existing, $me->id);
                }
                // Aceptada, bloqueada o ya enviada: no cambia nada y no se avisa de la diferencia.
            });
        } catch (QueryException) {
            // Carrera con otra solicitud del mismo par: la restricción única ya garantiza una sola fila.
        }
    }
}
