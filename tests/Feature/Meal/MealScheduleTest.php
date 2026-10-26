<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealSettings;
use App\Livewire\Meal\MealWeekly;
use App\Livewire\Task\TaskCalendar;
use App\Models\MealPlanEntry;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Meal\MealSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MealScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiles_defaults_validation_and_user_isolation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ModuleSetting::create(['module' => 'meals', 'settings' => ['other' => true]]);
        Livewire::test(MealSettings::class, ['section' => 'schedules'])
            ->assertSet('schedules.weekday.desayuno.time', '07:00')
            ->set('schedules.weekend.desayuno.time', '09:30')
            ->set('schedules.weekday.cena.duration', 0)->call('saveSchedules')->assertHasErrors()
            ->set('schedules.weekday.cena.duration', 45)->call('saveSchedules')->assertHasNoErrors();
        $this->assertTrue(ModuleSetting::first()->settings['other']);
        $service = app(MealSchedule::class);
        $profiles = $service->profiles($user->id);
        $this->assertSame('09:30', $service->resolve('2026-10-24', 'desayuno', $profiles)['start']->format('H:i'));
        $this->assertSame('07:00', $service->resolve('2026-10-23', 'desayuno', $profiles)['start']->format('H:i'));
        $this->actingAs(User::factory()->create());
        $this->assertSame(MealSchedule::defaults(), $service->profiles(auth()->id()));
        $this->get(route('meals.settings.schedules'))->assertOk();
    }

    public function test_events_override_range_summary_and_consumption_are_preserved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $entry = MealPlanEntry::create(['date' => '2026-10-24', 'meal_type' => 'cena', 'status' => 'consumed', 'consumed_at' => '2026-10-24 20:00:00', 'consumption' => ['calories' => 350]]);
        $entry->items()->create(['name' => '<Arroz>', 'position' => 0]);
        $before = $entry->fresh()->only(['status', 'consumed_at', 'consumption']);
        Livewire::test(MealWeekly::class)->call('openForm', '2026-10-24', 'cena')
            ->set('formTime', '23:30')->set('formDuration', 90)->call('saveSchedule')->assertHasNoErrors();
        $this->assertEquals($before, $entry->fresh()->only(['status', 'consumed_at', 'consumption']));
        $calendar = Livewire::test(TaskCalendar::class)->instance();
        $events = $calendar->events('2026-10-25T00:00:00', '2026-10-26T00:00:00');
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('meal:'.$entry->id, $event['id']);
        $this->assertSame('2026-10-25 01:00', Carbon::parse($event['end'])->format('Y-m-d H:i'));
        $this->assertSame('<Arroz>', $event['extendedProps']['summary']);
        $this->assertFalse($event['editable']);
        $this->assertSame([], $calendar->events($event['end'], '2026-10-26'));
        $this->assertSame([], $calendar->events('2026-10-23', $event['start']));
        Livewire::withQueryParams(['meal' => $entry->id])->test(MealWeekly::class)->assertSet('showForm', true)->assertSet('editingId', $entry->id);
        Livewire::test(MealWeekly::class)->call('openForm', '2026-10-24', 'cena')->call('resetSchedule')->call('saveSchedule');
        $this->assertNull($entry->fresh()->scheduled_time);
        $this->assertNull($entry->fresh()->duration_minutes);
        $this->actingAs(User::factory()->create());
        $this->assertSame([], Livewire::test(TaskCalendar::class)->instance()->events('2026-10-24', '2026-10-26'));
        Livewire::withQueryParams(['meal' => $entry->id])->test(MealWeekly::class)->assertNotFound();
    }

    public function test_new_meal_save_copy_and_move_preserve_schedule(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(MealWeekly::class)->call('openForm', '2026-10-23', 'desayuno')
            ->call('addRow')->set('formItems.0.kind', 'free')->set('formItems.0.name', 'Arroz')->set('formItems.0.portions', 1)->set('formItems.0.calories', 100)
            ->set('formTime', '08:45')->set('formDuration', 35)->call('save')->assertHasNoErrors();
        $entry = MealPlanEntry::firstOrFail();
        $this->assertSame('08:45', substr($entry->scheduled_time, 0, 5));
        $inventory = app(\App\Services\Meal\MealInventory::class);
        $inventory->rearrange(auth()->id(), $entry->id, 'copy', ['date' => '2026-10-24', 'meal_type' => 'desayuno']);
        $copy = MealPlanEntry::whereDate('date', '2026-10-24')->firstOrFail();
        $this->assertSame($entry->scheduled_time, $copy->scheduled_time);
        $this->assertSame(35, $copy->duration_minutes);
        $inventory->rearrange(auth()->id(), $copy->id, 'move', ['date' => '2026-10-25', 'meal_type' => 'cena']);
        $this->assertSame(35, $copy->fresh()->duration_minutes);
    }

    public function test_partial_configuration_and_independent_overrides(): void
    {
        $this->actingAs(User::factory()->create());
        ModuleSetting::create(['module' => 'meals', 'settings' => ['schedules' => ['weekend' => ['comida' => ['time' => '11:00']]]]]);
        $service = app(MealSchedule::class);
        $profiles = $service->profiles(auth()->id());
        $time = $service->resolve('2026-10-24', 'comida', $profiles, null, 40);
        $this->assertSame('11:00', $time['start']->format('H:i'));
        $this->assertSame(40, (int) $time['start']->diffInMinutes($time['end']));
        $time = $service->resolve('2026-10-24', 'comida', $profiles, '08:00');
        $this->assertSame('08:15', $time['end']->format('H:i'));
    }
}
