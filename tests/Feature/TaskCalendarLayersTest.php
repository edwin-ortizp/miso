<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskCalendar;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCalendarLayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_overlap_the_visible_range_with_an_inclusive_last_day_in_every_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (array_keys(Plan::STATUSES) as $status) {
            Plan::factory()->create(['user_id' => $user->id, 'title' => $status, 'status' => $status, 'scheduled_on' => '2026-10-05']);
        }
        $multi = Plan::factory()->create(['user_id' => $user->id, 'scheduled_on' => '2026-09-28', 'ends_on' => '2026-10-01']);
        Plan::factory()->create(['user_id' => $user->id, 'title' => 'At end', 'scheduled_on' => '2026-11-01']);
        Plan::factory()->create(['user_id' => $user->id, 'title' => 'Before', 'scheduled_on' => '2026-09-28', 'ends_on' => '2026-09-30']);
        Plan::factory()->create(['user_id' => $user->id, 'title' => 'No date']);
        Plan::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'Foreign', 'scheduled_on' => '2026-10-05']);
        $events = collect(Livewire::test(TaskCalendar::class)->instance()->events('2026-10-01T00:00:00-05:00', '2026-11-01T00:00:00-05:00'));
        $this->assertCount(5, $events);
        foreach (array_keys(Plan::STATUSES) as $status) {
            $event = $events->firstWhere('title', $status);
            $this->assertSame('2026-10-06', $event['end']);
            $this->assertTrue($event['allDay']);
            $this->assertFalse($event['editable']);
        }
        $event = $events->firstWhere('id', 'plan:'.$multi->id);
        $this->assertSame('2026-09-28', $event['start']);
        $this->assertSame('2026-10-02', $event['end']);
        $this->assertSame(route('plans.show', $multi), $event['extendedProps']['detailUrl']);
    }

    public function test_birthdays_use_the_occurrence_year_and_exclude_archived_foreign_and_outside_people(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $person = Relationship::factory()->withBirthday(1, 1, 1990)->create(['user_id' => $user->id, 'full_name' => 'Con año']);
        Relationship::factory()->withBirthday(12, 31)->create(['user_id' => $user->id, 'full_name' => 'Sin año']);
        Relationship::factory()->withBirthday(1, 2)->create(['user_id' => $user->id]);
        Relationship::factory()->withBirthday(1, 1)->archived()->create(['user_id' => $user->id]);
        Relationship::factory()->withBirthday(1, 1)->create(['user_id' => User::factory()->create()->id]);
        $events = collect(Livewire::test(TaskCalendar::class)->instance()->events('2026-12-31', '2027-01-02'));
        $this->assertCount(2, $events);
        $event = $events->firstWhere('title', 'Con año · 37 años');
        $this->assertSame('birthday:'.$person->id.':2027', $event['id']);
        $this->assertSame('2027-01-01', $event['start']);
        $this->assertSame('2027-01-02', $event['end']);
        $this->assertTrue($event['allDay']);
        $this->assertFalse($event['editable']);
        $this->assertSame(route('relationships.show', $person), $event['extendedProps']['detailUrl']);
        $this->assertNotNull($events->firstWhere('title', 'Sin año'));
        $this->assertDatabaseCount('relationship_events', 0);
    }

    public function test_february_29_is_derived_for_each_visible_year_without_changing_the_birthday(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $person = Relationship::factory()->withBirthday(2, 29, 2000)->create(['user_id' => $user->id]);
        $calendar = Livewire::test(TaskCalendar::class)->instance();
        $common = $calendar->events('2027-02-28', '2027-03-01');
        $leap = $calendar->events('2028-02-28', '2028-03-01');
        $this->assertSame('2027-02-28', $common[0]['start']);
        $this->assertSame('2028-02-29', $leap[0]['start']);
        $this->assertSame([], $calendar->events('2027-02-27', '2027-02-28'));
        $this->assertSame([], $calendar->events('2028-02-28', '2028-02-29'));
        $this->assertCount(2, $calendar->events('2027-01-01', '2029-01-01'));
        $this->assertSame(29, $person->fresh()->birthday_day);
    }

    public function test_new_sources_are_read_once_even_with_many_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Plan::factory()->count(8)->create(['user_id' => $user->id, 'scheduled_on' => '2026-10-05']);
        Relationship::factory()->count(8)->withBirthday(10, 5)->create(['user_id' => $user->id]);
        $calendar = Livewire::test(TaskCalendar::class)->instance();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertCount(16, $calendar->events('2026-10-01', '2026-11-01'));
            $queries = collect(DB::getQueryLog())->pluck('query');
            foreach (['plans', 'relationships'] as $table) {
                $this->assertCount(1, $queries->filter(fn ($sql) => preg_match('/from ["`]?'.$table.'["`]? /i', $sql)));
            }
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_the_feed_requires_authentication_even_without_the_model_scope(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new TaskCalendar)->events('2026-10-01', '2026-11-01');
    }
}
