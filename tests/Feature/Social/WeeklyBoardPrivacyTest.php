<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Services\Social\FriendSnapshot;
use App\Services\Social\Friends;
use App\Services\Social\SocialSettings;

class WeeklyBoardPrivacyTest extends FriendTestCase
{
    public function test_removed_or_blocked_friends_disappear_from_the_board(): void
    {
        [$ana, $luis, $sofi] = User::factory()->count(3)->create();
        $removed = $this->befriend($ana, $luis);
        $blocked = $this->befriend($ana, $sofi);
        foreach ([$ana, $luis, $sofi] as $user) {
            app(SocialSettings::class)->setVisible($user->id, true);
            app(SocialSettings::class)->setLeaderboard($user->id, true);
        }
        $this->assertCount(3, app(FriendSnapshot::class)->weekly($ana));

        app(Friends::class)->remove($ana, $removed->id);
        app(Friends::class)->block($sofi, $blocked->id);

        $this->assertSame([$ana->id], array_column(app(FriendSnapshot::class)->weekly($ana), 'id'));
    }

    public function test_the_two_settings_are_independent_of_each_other_and_default_off(): void
    {
        $user = User::factory()->create();
        $settings = app(SocialSettings::class);

        $this->assertFalse($settings->leaderboard($user->id));
        $settings->setLeaderboard($user->id, true);
        $this->assertFalse($settings->visible($user->id));
        $settings->setVisible($user->id, true);
        $this->assertTrue($settings->leaderboard($user->id));
    }
}
