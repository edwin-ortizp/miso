<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\Friends;
use Illuminate\Support\Facades\RateLimiter;

class FriendRequestTest extends FriendTestCase
{
    public function test_request_by_code_and_by_username_creates_one_pending_row(): void
    {
        $ana = User::factory()->create();
        $luis = User::factory()->create(['username' => 'luis_99']);
        $sofi = User::factory()->create();
        $friends = app(Friends::class);

        $this->assertSame(Friends::OK, $friends->request($ana, 'MISO-'.strtolower($luis->friend_code)));
        $this->assertSame(Friends::OK, $friends->request($ana, '@Luis_99'));
        $this->assertSame(Friends::OK, $friends->request($ana, $sofi->friend_code));

        $this->assertSame(2, Friendship::count());
        $this->assertSame(Friendship::PENDING, Friendship::between($ana->id, $luis->id)->value('status'));
    }

    public function test_crossed_request_becomes_accepted_without_duplicates(): void
    {
        $ana = User::factory()->create(['username' => 'ana_x']);
        $luis = User::factory()->create(['username' => 'luis_x']);
        $friends = app(Friends::class);

        $friends->request($ana, '@luis_x');
        $friends->request($luis, '@ana_x');

        $this->assertSame(1, Friendship::count());
        $this->assertSame(Friendship::ACCEPTED, Friendship::first()->status);
        $this->assertCount(1, $friends->friends($ana));
    }

    public function test_repeating_a_request_does_not_duplicate(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friends = app(Friends::class);

        $friends->request($ana, $luis->friend_code);
        $friends->request($ana, $luis->friend_code);

        $this->assertSame(1, Friendship::count());
    }

    public function test_accept_only_by_addressee_and_decline_removes(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friends = app(Friends::class);
        $friends->request($ana, $luis->friend_code);
        $id = Friendship::first()->id;

        $this->assertFalse($friends->accept($ana, $id));
        $this->assertTrue($friends->accept($luis, $id));
        $this->assertSame(Friendship::ACCEPTED, Friendship::first()->status);
    }

    public function test_cannot_add_yourself(): void
    {
        $ana = User::factory()->create();
        app(Friends::class)->request($ana, $ana->friend_code);

        $this->assertSame(0, Friendship::count());
    }

    public function test_friend_limit(): void
    {
        $ana = User::factory()->create();
        $target = User::factory()->create();
        foreach (User::factory()->count(3)->create() as $other) {
            Friendship::create(['requester_id' => $ana->id, 'addressee_id' => $other->id, 'status' => Friendship::ACCEPTED]);
        }
        $this->assertSame(3, app(Friends::class)->count($ana->id));

        // Simula el tope sin crear 100 cuentas.
        $friends = new class extends Friends {
            public function count(int $userId, bool $accepted = false): int
            {
                return self::LIMIT;
            }
        };
        $this->assertSame(Friends::LIMIT_REACHED, $friends->request($ana, $target->friend_code));
        $this->assertSame(3, Friendship::count());
    }

    public function test_lookup_is_throttled(): void
    {
        $ana = User::factory()->create();
        $friends = app(Friends::class);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(Friends::OK, $friends->request($ana, 'ZZZZZZZZ'));
        }

        $this->assertSame(Friends::THROTTLED, $friends->request($ana, 'ZZZZZZZZ'));
        RateLimiter::clear("friend-lookup:user:{$ana->id}");
    }

    public function test_unknown_and_unavailable_targets_get_the_same_answer(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $friends = app(Friends::class);
        $friends->request($luis, $ana->friend_code);
        $friends->block($ana, Friendship::first()->id);

        $existing = $friends->request($ana, $luis->friend_code);
        $unknown = $friends->request($ana, 'NOEXISTE');

        $this->assertSame($existing, $unknown);
    }
}
