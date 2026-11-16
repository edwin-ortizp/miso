<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\Push\NotificationDispatcher;
use App\Services\Social\FriendNotices;
use App\Services\Social\Friends;

class FriendNotificationTest extends FriendTestCase
{
    private function notices(User $user)
    {
        return UserNotification::withoutGlobalScopes()->where('user_id', $user->id)->where('source_type', FriendNotices::SOURCE);
    }

    public function test_a_request_notifies_the_addressee_once_even_if_repeated(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friends = app(Friends::class);

        $friends->request($ana, $luis->friend_code);
        $friends->request($ana, $luis->friend_code);
        // Rechazar y reenviar: el par normalizado evita un aviso nuevo.
        $friends->decline($luis, Friendship::first()->id);
        $friends->request($ana, $luis->friend_code);

        $this->assertSame(1, $this->notices($luis)->where('type', FriendNotices::REQUEST)->count());
        $this->assertSame(0, $this->notices($ana)->count());
    }

    public function test_accepting_notifies_the_requester(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        app(Friends::class)->request($ana, $luis->friend_code);
        app(Friends::class)->accept($luis, Friendship::first()->id);

        $notice = $this->notices($ana)->where('type', FriendNotices::ACCEPTED)->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString($luis->name, $notice->title);
        $this->assertSame(route('social.friends'), $notice->destination());
    }

    public function test_crossed_request_notifies_the_original_requester(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        app(Friends::class)->request($ana, $luis->friend_code);
        app(Friends::class)->request($luis, $ana->friend_code);

        $this->assertSame(1, $this->notices($ana)->where('type', FriendNotices::ACCEPTED)->count());
    }

    public function test_shared_milestone_notifies_both_once(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $notices = app(FriendNotices::class);

        $notices->sharedStreak($ana, $luis->id, 7);
        $notices->sharedStreak($luis, $ana->id, 7);
        $notices->sharedStreak($ana, $luis->id, 7);
        $notices->sharedStreak($ana, $luis->id, 30);

        $this->assertSame(2, $this->notices($ana)->where('type', FriendNotices::STREAK)->count());
        $this->assertSame(2, $this->notices($luis)->where('type', FriendNotices::STREAK)->count());
    }

    public function test_preference_off_creates_no_notice(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        app(NotificationPreferences::class)->save($luis->id, ['friend_notices' => false]);
        app(Friends::class)->request($ana, $luis->friend_code);

        $this->assertSame(0, $this->notices($luis)->count());
    }

    public function test_blocked_pair_creates_no_notice(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friendship = $this->befriend($ana, $luis);
        app(Friends::class)->block($luis, $friendship->id);

        app(FriendNotices::class)->sharedStreak($ana, $luis->id, 7);
        app(Friends::class)->request($ana, $luis->friend_code);

        $this->assertSame(0, UserNotification::withoutGlobalScopes()->where('source_type', FriendNotices::SOURCE)->count());
    }

    public function test_friend_notice_push_only_offers_open_and_respects_the_push_preference(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        app(Friends::class)->request($ana, $luis->friend_code);
        $notice = $this->notices($luis)->first();

        $actions = NotificationDispatcher::actions($notice);
        $this->assertSame(['open'], array_column($actions['actions'], 'action'));
        $prefs = app(NotificationPreferences::class)->get($luis->id);
        $this->assertFalse(NotificationPreferences::pushAllowed($prefs, FriendNotices::REQUEST));
    }
}
