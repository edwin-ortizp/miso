<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\FriendSnapshot;
use App\Services\Social\Friends;
use App\Services\Social\SocialSettings;

class FriendBlockTest extends FriendTestCase
{
    public function test_snapshot_requires_friendship_and_visibility(): void
    {
        [$ana, $luis, $tercero] = User::factory()->count(3)->create();
        $this->befriend($ana, $luis);
        $snapshot = app(FriendSnapshot::class);

        $this->assertNull($snapshot->for($ana, $luis), 'visible apagado por defecto');

        app(SocialSettings::class)->setVisible($luis->id, true);
        $data = $snapshot->for($ana, $luis);
        $this->assertSame(['id', 'username', 'name', 'avatar_url'], array_keys($data));
        $this->assertNull($snapshot->for($tercero, $luis), 'un tercero no obtiene nada');
    }

    public function test_remove_and_block_cut_every_read(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friendship = $this->befriend($ana, $luis);
        app(SocialSettings::class)->setVisible($luis->id, true);
        $friends = app(Friends::class);
        $snapshot = app(FriendSnapshot::class);

        $friends->block($luis, $friendship->id);
        $this->assertNull($snapshot->for($ana, $luis));
        $this->assertCount(0, $friends->friends($ana));
        $this->assertCount(1, $friends->blocked($luis));
        $this->assertCount(0, $friends->blocked($ana), 'quien fue bloqueado no ve el bloqueo');

        $friends->unblock($ana, $friendship->id);
        $this->assertSame(1, Friendship::count(), 'solo quien bloqueó puede desbloquear');
        $friends->unblock($luis, $friendship->id);
        $this->assertSame(0, Friendship::count());
    }

    public function test_blocked_person_cannot_reopen_a_request(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friendship = $this->befriend($ana, $luis);
        app(Friends::class)->block($luis, $friendship->id);

        app(Friends::class)->request($ana, $luis->friend_code);

        $this->assertSame(Friendship::BLOCKED, Friendship::first()->status);
    }

    public function test_remove_deletes_friendship(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friendship = $this->befriend($ana, $luis);
        app(Friends::class)->remove($luis, $friendship->id);

        $this->assertSame(0, Friendship::count());
    }
}
