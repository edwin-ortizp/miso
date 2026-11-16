<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Services\Social\SharedStreak;
use App\Services\Social\SocialSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SharedStreakTest extends FriendTestCase
{
    private const TODAY = '2026-11-20';

    private User $ana;
    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::TODAY.' 10:00:00', 'America/Bogota');
        [$this->ana, $this->luis] = User::factory()->count(2)->create();
        $this->befriend($this->ana, $this->luis);
        foreach ([$this->ana, $this->luis] as $user) {
            app(SocialSettings::class)->setVisible($user->id, true);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Fija el resultado de un día cerrado para una cuenta (como lo haría Streak::compute). */
    private function day(User $user, string $daysAgo, bool $met): void
    {
        DB::table('user_active_days')->insert([
            'user_id' => $user->id, 'date' => Carbon::parse(self::TODAY)->subDays((int) $daysAgo)->toDateString(),
            'first_channel' => 'web', 'records_count' => $met ? 5 : 0, 'streak_met' => $met, 'streak_threshold' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function both(string $daysAgo, bool $ana = true, bool $luis = true): void
    {
        $this->day($this->ana, $daysAgo, $ana);
        $this->day($this->luis, $daysAgo, $luis);
    }

    private function streak(): ?array
    {
        return app(SharedStreak::class)->for($this->ana, $this->luis->id);
    }

    public function test_days_where_both_met_build_the_streak(): void
    {
        $this->both('3');
        $this->both('2');
        $this->both('1');

        $this->assertSame(3, $this->streak()['days']);
        $this->assertSame(3, $this->streak()['best']);
    }

    public function test_one_missing_uses_a_grace_and_keeps_the_streak(): void
    {
        $this->both('3');
        $this->both('2', luis: false);
        $this->both('1');

        $streak = $this->streak();
        $this->assertSame(2, $streak['days']);
        $this->assertSame(1, $streak['grace_total'] - $streak['grace_left']);
        $this->assertSame('grace', $streak['last7'][4]['status']);
    }

    public function test_break_without_graces_restarts_and_keeps_the_best(): void
    {
        foreach (['9', '8', '7', '6'] as $d) {
            $this->both($d);
        }
        $this->both('5', luis: false);
        $this->both('4', luis: false);
        $this->both('3', luis: false); // tercera ausencia del mes: sin gracia
        $this->both('1');

        $streak = $this->streak();
        $this->assertSame(1, $streak['days']);
        $this->assertSame(4, $streak['best']);
    }

    public function test_the_current_day_never_breaks_the_streak(): void
    {
        $this->both('2');
        $this->both('1');

        $streak = $this->streak();
        $this->assertSame(2, $streak['days']);
        $this->assertFalse($streak['met_today']);
        $this->assertSame('today', $streak['last7'][6]['status']);
    }

    public function test_pending_days_use_the_current_threshold_and_stored_days_keep_theirs(): void
    {
        $this->both('2');
        // Día ayer sin fijar para Luis (streak_met NULL) pero con muy pocos registros: no cumple.
        $this->day($this->ana, '1', true);
        DB::table('user_active_days')->insert([
            'user_id' => $this->luis->id, 'date' => Carbon::parse(self::TODAY)->subDay()->toDateString(),
            'first_channel' => 'web', 'records_count' => 1, 'streak_met' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('grace', $this->streak()['last7'][5]['status']);
        $this->assertNull(DB::table('user_active_days')->where('user_id', $this->luis->id)->whereNull('streak_met')->value('streak_met'));
        $this->assertSame(1, DB::table('user_active_days')->where('user_id', $this->luis->id)->whereNull('streak_met')->count(), 'leer no escribe');
    }

    public function test_milestones(): void
    {
        $shared = app(SharedStreak::class);
        $this->assertNull($shared->milestoneReached(6));
        $this->assertSame(7, $shared->milestoneReached(7));
        $this->assertSame(30, $shared->milestoneReached(45));
        $this->assertSame(100, $shared->milestoneReached(400));
    }
}
