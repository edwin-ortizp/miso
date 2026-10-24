<?php

namespace Tests\Feature;

use App\Livewire\Goal\GoalIndex;
use App\Livewire\Task\TaskEditor;
use App\Models\Goal;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\GenerateNotifications;
use App\Services\Notifications\ManageReminders;
use App\Services\Notifications\NotificationPreferences;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_only_uses_current_rules_and_is_idempotent(): void
    {
        $this->travelTo(Carbon::parse('2026-02-28 08:00', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        app(NotificationPreferences::class)->save($user->id, ['birthdays' => true, 'tasks' => true, 'plans' => true]);
        Relationship::create(['category' => 'friends', 'full_name' => 'Leap', 'birthday_month' => 2, 'birthday_day' => 29]);
        Relationship::create(['category' => 'friends', 'full_name' => 'Archived', 'birthday_month' => 2, 'birthday_day' => 28, 'is_archived' => true]);
        Task::create(['title' => 'Tomorrow', 'end_date' => '2026-03-01', 'end_is_date' => true]);
        Task::create(['title' => 'Past', 'end_date' => '2026-02-27', 'end_is_date' => true]);
        Plan::create(['title' => 'Tomorrow', 'type' => 'activity', 'status' => 'scheduled', 'scheduled_on' => '2026-03-01']);
        Goal::create(['title' => 'No automatic goal', 'status' => 'active', 'due_date' => '2026-03-01']);
        $generator = app(GenerateNotifications::class);
        $this->assertSame(0, $generator->run($user)['created']);
        $this->travelTo(Carbon::parse('2026-02-28 09:00', 'America/Bogota'));
        $this->assertSame(3, $generator->run($user)['created']);
        $this->assertSame(0, $generator->run($user)['created']);
        $this->assertSame(3, UserNotification::count());
        $this->assertSame('2026-02-28 14:00', UserNotification::first()->scheduled_for->format('Y-m-d H:i'));
        $this->travel(5)->days();
        $this->assertSame(0, $generator->run($user)['created']);
        $this->assertSame(3, UserNotification::count());
    }

    public function test_reminders_support_calendar_units_and_cancel_on_date_changes_completion_and_removal(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 06:00', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Reminder', 'end_date' => '2026-10-24', 'end_is_date' => true]);
        $manager = app(ManageReminders::class);
        $manager->sync($task, [['amount' => 2, 'unit' => 'hours'], ['amount' => 30, 'unit' => 'minutes']]);
        $this->assertSame(2, Reminder::count());
        $this->assertSame(0, UserNotification::count());
        $this->assertSame(0, app(GenerateNotifications::class)->run($user)['created']);
        $this->travelTo(Carbon::parse('2026-10-24 07:00', 'America/Bogota'));
        $this->assertSame(1, app(GenerateNotifications::class)->run($user)['created']);
        $this->travelTo(Carbon::parse('2026-10-24 08:30', 'America/Bogota'));
        app(GenerateNotifications::class)->run($user);
        $this->assertSame(2, UserNotification::whereNull('cancelled_at')->count());
        $this->assertTrue(UserNotification::get()->contains(fn ($r) => $r->scheduled_for->timezone('America/Bogota')->format('Y-m-d H:i') === '2026-10-24 07:00'));
        $this->assertTrue(Reminder::first()->source->is($task));
        $this->assertSame(2, $task->reminders()->count());
        $this->assertSame(2, $task->notifications()->count());
        $this->assertTrue(UserNotification::with('source')->first()->source->is($task));
        $this->assertCount(2, Task::with('reminders')->findOrFail($task->id)->reminders);
        $task->update(['end_date' => '2026-10-25']);
        $this->assertSame(2, UserNotification::whereNotNull('cancelled_at')->count());
        $this->assertSame(0, UserNotification::whereNull('cancelled_at')->count());
        $this->travel(1)->days();
        app(GenerateNotifications::class)->run($user);
        $this->assertSame(2, UserNotification::whereNull('cancelled_at')->count());
        $rows = $manager->rows($task);
        $manager->sync($task, [$rows[0]]);
        $this->assertSame(1, UserNotification::whereNull('cancelled_at')->count());
        $task->update(['completed' => true]);
        $this->assertSame(0, UserNotification::whereNull('cancelled_at')->count());
        $task->update(['completed' => false]);
        app(GenerateNotifications::class)->run($user);
        $this->assertSame(1, UserNotification::whereNull('cancelled_at')->count());
        $task->delete();
        $this->assertSame(0, Reminder::count());
        $this->assertSame('2027-02-28 09:00', $manager->calculate(CarbonImmutable::parse('2027-03-31 09:00', 'America/Bogota'), 1, 'months')->timezone('America/Bogota')->format('Y-m-d H:i'));
    }

    public function test_web_editor_validates_anchor_and_persists_reminders_with_source(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(TaskEditor::class)->call('openForm')->set('title', 'Task reminder')->set('reminders', [['amount' => 3, 'unit' => 'days']])
            ->call('save')->assertHasErrors('reminders')->set('endDate', '2026-11-01')->call('save')->assertHasNoErrors();
        $this->assertSame(1, Reminder::count());
        Livewire::test(GoalIndex::class)->call('openForm')->set('title', 'Goal reminder')->set('dueDate', '2026-11-01')
            ->set('reminders', [['amount' => 1, 'unit' => 'months']])->call('save')->assertHasNoErrors();
        $this->assertSame(2, Reminder::count());
    }

    public function test_plan_editor_saves_reminders_and_foreign_reminder_ids_rollback_the_edit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(\App\Livewire\Plan\PlanIndex::class)->call('openPlanForm')
            ->set('planTitle', 'Plan con aviso')->set('planType', 'activity')->set('planScheduledOn', '2026-11-01')
            ->set('reminders', [['amount' => 2, 'unit' => 'weeks']])->call('savePlan')->assertHasNoErrors();
        $this->assertSame(1, Reminder::count());
        $other = User::factory()->create();
        $goal = new Goal(['title' => 'Ajena', 'status' => 'active', 'due_date' => '2026-11-01']);
        $goal->user_id = $other->id;
        $goal->save();
        app(ManageReminders::class)->sync($goal, [['amount' => 1, 'unit' => 'days']]);
        $foreignId = Reminder::withoutGlobalScopes()->where('user_id', $other->id)->value('id');
        $own = Goal::create(['title' => 'Original', 'status' => 'active', 'due_date' => '2026-11-01']);
        Livewire::test(GoalIndex::class)->call('openForm', $own->id)->set('title', 'No debe persistir')
            ->set('reminders', [['id' => $foreignId, 'amount' => 1, 'unit' => 'days']])->call('save')->assertNotFound();
        $this->assertSame('Original', $own->fresh()->title);
    }

    public function test_explicit_reminder_does_not_rebuild_a_past_occurrence(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Past', 'status' => 'active', 'due_date' => '2026-10-01']);
        app(ManageReminders::class)->sync($goal, [['amount' => 1, 'unit' => 'days']]);
        $this->assertSame(0, UserNotification::count());
        $this->assertSame(0, app(GenerateNotifications::class)->run($user)['created']);
    }
    public function test_cron_without_authentication_isolates_owners_and_birthdays_repeat_yearly(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 09:00', 'America/Bogota'));
        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            $this->actingAs($user);
            app(NotificationPreferences::class)->save($user->id, ['birthdays' => true]);
            Relationship::create(['category' => 'friends', 'full_name' => 'Birthday', 'birthday_month' => 10, 'birthday_day' => 24]);
        }
        auth()->forgetGuards();
        $this->artisan('notifications:generate', ['--user' => $users[0]->id])->assertSuccessful();
        $this->assertSame(1, UserNotification::withoutGlobalScopes()->where('user_id', $users[0]->id)->count());
        $this->assertSame(0, UserNotification::withoutGlobalScopes()->where('user_id', $users[1]->id)->count());
        $this->artisan('notifications:generate')->assertSuccessful();
        $this->assertSame(2, UserNotification::withoutGlobalScopes()->count());
        $this->travelTo(Carbon::parse('2027-10-24 09:00', 'America/Bogota'));
        $this->artisan('notifications:generate')->assertSuccessful();
        $this->assertSame(4, UserNotification::withoutGlobalScopes()->count());
    }

    public function test_duplicate_anticipations_are_rejected_and_retained_ids_can_swap(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Reminders', 'end_date' => '2026-11-01', 'end_is_date' => true]);
        $manager = app(ManageReminders::class);
        $manager->sync($task, [['amount' => 1, 'unit' => 'days'], ['amount' => 2, 'unit' => 'days']]);
        $rows = $manager->rows($task);
        [$rows[0]['amount'], $rows[1]['amount']] = [$rows[1]['amount'], $rows[0]['amount']];
        $manager->sync($task, $rows);
        $this->assertSame(2, $task->reminders()->count());
        foreach ($rows as $row) {
            $this->assertSame($row['amount'], Reminder::findOrFail($row['id'])->amount);
        }
        try {
            $manager->sync($task, [['amount' => 1, 'unit' => 'days'], ['amount' => 1, 'unit' => 'days']]);
            $this->fail('Duplicate reminders must fail validation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('reminders', $exception->errors());
        }
        $this->assertSame(2, $task->reminders()->count());
    }

    public function test_plan_and_goal_reminders_use_real_polymorphic_relations(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 09:00', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = Plan::create(['title' => 'Plan', 'type' => 'activity', 'status' => 'scheduled', 'scheduled_on' => '2026-10-25']);
        $goal = Goal::create(['title' => 'Goal', 'status' => 'active', 'due_date' => '2026-10-25']);
        foreach ([$plan, $goal] as $source) {
            app(ManageReminders::class)->sync($source, [['amount' => 1, 'unit' => 'days']]);
            $this->assertTrue($source->reminders()->first()->source->is($source));
        }
        $this->assertSame(2, app(GenerateNotifications::class)->run($user)['created']);
        foreach ([$plan, $goal] as $source) {
            $notice = $source->notifications()->firstOrFail();
            $this->assertTrue($notice->source->is($source));
            $this->assertStringContainsString($source->id, $notice->destination());
        }
        $plan->update(['status' => 'done']);
        $goal->update(['status' => 'completed']);
        $this->assertSame(0, UserNotification::whereNull('cancelled_at')->count());
    }

}
