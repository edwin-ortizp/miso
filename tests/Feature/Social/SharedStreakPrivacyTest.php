<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\Friends;
use App\Services\Social\SharedStreak;
use App\Services\Social\SocialSettings;

class SharedStreakPrivacyTest extends FriendTestCase
{
    public function test_hidden_unless_friends_and_both_visible(): void
    {
        [$ana, $luis, $tercero] = User::factory()->count(3)->create();
        $friendship = $this->befriend($ana, $luis);
        $shared = app(SharedStreak::class);
        $settings = app(SocialSettings::class);

        $this->assertNull($shared->for($ana, $luis->id), 'ninguno visible');
        $settings->setVisible($ana->id, true);
        $this->assertNull($shared->for($ana, $luis->id), 'solo yo visible');
        $settings->setVisible($luis->id, true);
        $this->assertIsArray($shared->for($ana, $luis->id));
        $this->assertNull($shared->for($tercero, $luis->id), 'un tercero no ve nada');

        app(Friends::class)->remove($ana, $friendship->id);
        $this->assertNull($shared->for($ana, $luis->id), 'amistad eliminada');
    }

    public function test_blocked_friendship_hides_the_streak(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friendship = $this->befriend($ana, $luis);
        app(SocialSettings::class)->setVisible($ana->id, true);
        app(SocialSettings::class)->setVisible($luis->id, true);
        app(Friends::class)->block($luis, $friendship->id);

        $this->assertNull(app(SharedStreak::class)->for($ana, $luis->id));
        $this->assertSame(Friendship::BLOCKED, Friendship::first()->status);
    }
}
