<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Social\FriendNotices;
use App\Services\Social\SocialSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FriendMilestonesCommandTest extends FriendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00', 'America/Bogota');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Ambos cumplieron los últimos $days días cerrados. */
    private function streakOf(User $a, User $b, int $days): void
    {
        foreach ([$a, $b] as $user) {
            for ($i = 1; $i <= $days; $i++) {
                DB::table('user_active_days')->insertOrIgnore([
                    'user_id' => $user->id, 'date' => Carbon::parse('2026-11-20')->subDays($i)->toDateString(),
                    'first_channel' => 'web', 'records_count' => 5, 'streak_met' => true, 'streak_threshold' => 3,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function show(User ...$users): void
    {
        foreach ($users as $user) {
            app(SocialSettings::class)->setVisible($user->id, true);
        }
    }

    private function count(): int
    {
        return UserNotification::withoutGlobalScopes()->where('type', FriendNotices::STREAK)->count();
    }

    public function test_running_twice_notifies_each_person_once(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $this->befriend($ana, $luis);
        $this->show($ana, $luis);
        $this->streakOf($ana, $luis, 8);

        Artisan::call('miso:friend-milestones');
        Artisan::call('miso:friend-milestones');

        $this->assertSame(2, $this->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $this->befriend($ana, $luis);
        $this->show($ana, $luis);
        $this->streakOf($ana, $luis, 8);

        Artisan::call('miso:friend-milestones', ['--dry-run' => true]);

        $this->assertSame(0, $this->count());
        $this->assertStringContainsString('en un hito: 1', Artisan::output());
    }

    public function test_hidden_blocked_or_short_streaks_do_not_notify(): void
    {
        [$ana, $luis, $sofi, $pau] = User::factory()->count(4)->create();
        $this->befriend($ana, $luis);           // luis no es visible
        $this->show($ana, $sofi, $pau);
        $blocked = $this->befriend($ana, $sofi);
        $this->befriend($ana, $pau);            // racha corta
        $this->streakOf($ana, $luis, 8);
        $this->streakOf($ana, $sofi, 8);
        $this->streakOf($ana, $pau, 3);
        app(\App\Services\Social\Friends::class)->block($sofi, $blocked->id);

        Artisan::call('miso:friend-milestones');

        $this->assertSame(0, $this->count());
    }
}
