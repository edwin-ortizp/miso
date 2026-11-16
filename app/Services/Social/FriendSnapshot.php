<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\User;

/**
 * Lo único que un amigo puede ver de otra cuenta (CHG-121): usuario, nombre visible y avatar.
 * Nunca consulta tareas, hábitos, diario, salud ni Relaciones.
 */
class FriendSnapshot
{
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
}
