<?php

namespace Tests\Feature\Engagement;

use App\Livewire\Home\Dashboard;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use App\Services\Notifications\Push\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-069: check-in de 20 s y acciones del aviso push. */
class QuickCheckinTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 15:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        DrinkType::create(['name' => 'Agua', 'hydration_factor' => 1, 'icon' => '💧']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function notice(string $module): UserNotification
    {
        return UserNotification::withoutGlobalScopes()->create([
            'user_id' => $this->user->id, 'source_type' => GuardianNudgePlanner::SOURCE,
            'source_id' => GuardianNudgePlanner::sourceId($module), 'type' => GuardianNudgePlanner::TYPE,
            'occurrence_key' => (string) Str::uuid(), 'title' => 'MiSo', 'body' => 'Te falta un vaso.',
            'scheduled_for' => now('UTC'),
        ]);
    }

    public function test_checkin_url_opens_sheet_with_all_blocks(): void
    {
        HabitDefinition::create(['name' => 'Leer 20 minutos', 'icon' => '📚', 'time_of_day' => 'anytime', 'base_time' => '16:00']);

        $this->get('/?checkin=1&via=checkin')->assertOk()
            ->assertSee('¿Cómo te sientes?')->assertSee('Energía')->assertSee('+250 ml')->assertSee('Leer 20 minutos');
        Livewire::withQueryParams(['checkin' => 1])->test(Dashboard::class)->assertSet('checkin', true);
    }

    public function test_checkin_logs_energy_water_and_habit_for_today(): void
    {
        $habit = HabitDefinition::create(['name' => 'Meditar', 'icon' => '🧘', 'time_of_day' => 'anytime', 'base_time' => '16:00']);

        Livewire::withQueryParams(['checkin' => 1, 'date' => '2026-11-01'])->test(Dashboard::class)
            ->assertSet('selectedDate', '2026-11-11')
            ->call('saveEnergy', 4)
            ->call('quickAddWater', DrinkType::first()->id, 250)
            ->call('toggleHabit', $habit->id)
            ->call('closeCheckin')->assertSet('checkin', false);

        $this->assertSame(4, (int) EnergyEntry::first()->level);
        $this->assertSame(1, DrinkLog::count());
        $this->assertTrue((bool) HabitCompletion::where('habit_id', $habit->id)->value('completed'));
    }

    public function test_signed_quick_water_logs_once(): void
    {
        $notice = $this->notice('water');
        $url = URL::temporarySignedRoute('push.quick-water', now()->addHours(12), ['notification' => $notice->id]);
        auth()->logout();

        $this->postJson($url)->assertOk()->assertJson(['status' => 'logged']);
        $this->postJson($url)->assertOk()->assertJson(['status' => 'already-done']);

        $this->assertSame(1, DrinkLog::withoutGlobalScopes()->where('user_id', $this->user->id)->count());
    }

    public function test_invalid_or_expired_signature_is_rejected(): void
    {
        $notice = $this->notice('water');
        $expired = URL::temporarySignedRoute('push.quick-water', now()->subMinute(), ['notification' => $notice->id]);
        auth()->logout();

        $this->postJson(route('push.quick-water', ['notification' => $notice->id]))->assertForbidden();
        $this->postJson($expired)->assertForbidden();
        $this->assertSame(0, DrinkLog::withoutGlobalScopes()->count());
    }

    public function test_payload_actions_depend_on_module(): void
    {
        $water = NotificationDispatcher::actions($this->notice('water'));
        $habits = NotificationDispatcher::actions($this->notice('habits'));

        $this->assertSame('quick-water', $water['actions'][0]['action']);
        $this->assertArrayHasKey('quick_url', $water);
        $this->assertSame('checkin', $habits['actions'][0]['action']);
        $this->assertStringContainsString('checkin=1', $habits['checkin_url']);
    }
}
