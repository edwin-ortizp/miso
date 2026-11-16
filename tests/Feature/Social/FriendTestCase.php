<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

abstract class FriendTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('friend-lookup:ip:cli');
    }

    protected function befriend(User $a, User $b): Friendship
    {
        return Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => Friendship::ACCEPTED]);
    }
}
