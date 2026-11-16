<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\Game\GameEvent;
use App\Models\User;
use App\Services\Game\WeekPlanner;
use Carbon\CarbonImmutable;

/**
 * Lo único que un amigo puede ver de otra cuenta (CHG-121): usuario, nombre visible y avatar.
 * Nunca consulta tareas, hábitos, diario, salud ni Relaciones.
 */
class FriendSnapshot
{
    public const WEEKLY_LIMIT = 100;

    public function __construct(private SocialSettings $settings)
    {
    }

    /** @return array{id: int, username: ?string, name: string, avatar_url: ?string}|null */
    public function for(User|int $viewer, User|int $friend): ?array
    {
        $viewerId = $viewer instanceof User ? $viewer->id : $viewer;
        $friendId = $friend instanceof User ? $friend->id : $friend;

        $accepted = Friendship::query()->between($viewerId, $friendId)->where('status', Friendship::ACCEPTED)->exists();
        if (! $accepted || ! $this->settings->visible($friendId)) {
            return null;
        }

        $user = $friend instanceof User ? $friend : User::find($friendId);

        return $user ? [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->full_name ?: $user->name,
            'avatar_url' => $user->avatar_url,
        ] : null;
    }

    /**
     * Tabla semanal (CHG-123): yo y los amigos aceptados que activaron «visible» y «mostrar mi semana».
     * Solo sale un número por persona; nunca el origen ni el contenido de las acciones.
     *
     * @return array<int, array{id: int, name: string, username: ?string, avatar_url: ?string, actions: int, me: bool}>
     */
    public function weekly(User $me, ?CarbonImmutable $now = null): array
    {
        if (! $this->settings->visible($me->id) || ! $this->settings->leaderboard($me->id)) {
            return [];
        }
        $friendIds = Friendship::query()->forUser($me->id)->where('status', Friendship::ACCEPTED)->get()
            ->map(fn (Friendship $f) => $f->otherId($me->id))
            ->filter(fn (int $id) => $this->settings->visible($id) && $this->settings->leaderboard($id))
            ->take(self::WEEKLY_LIMIT)->values();
        $ids = $friendIds->prepend($me->id)->all();

        $start = WeekPlanner::weekStart($now ?? CarbonImmutable::now(config('app.timezone')));
        $counts = GameEvent::withoutGlobalScopes()
            ->where('kind', GameEvent::KIND_ACTIVITY)->whereIn('user_id', $ids)
            ->whereBetween('occurred_at', [$start, $start->addDays(6)->endOfDay()])
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return User::whereIn('id', $ids)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'actions' => (int) ($counts[$user->id] ?? 0),
            'me' => $user->id === $me->id,
        ])->sortBy([['actions', 'desc'], ['name', 'asc']])->values()->all();
    }
}
