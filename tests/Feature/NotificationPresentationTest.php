<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Insights\WeeklyInsights;
use App\Services\Notifications\CreateNotification;
use App\Services\Notifications\NotificationPresentation;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'America/Bogota'));
    }

    private function notice($user, $source, string $type, string $occurrence): UserNotification
    {
        return app(CreateNotification::class)->create($user, $source, $type, $occurrence, 'Tarea para mañana', 'Tienes una tarea que vence mañana.', now()->subDay());
    }

    public function test_historical_notices_have_real_context_dates_and_scoped_deep_links_without_writes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Preparar propuesta comercial', 'end_date' => '2026-11-12', 'end_is_date' => true]);
        $notice = $this->notice($user, $task, 'task_due', '2026-11-12 09:00:00');
        $before = $notice->fresh()->getAttributes();
        $row = app(NotificationPresentation::class)->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertSame($task->title, $row['title']);
        $this->assertStringContainsString('Vence mañana, 12 nov', $row['context']);
        $this->assertStringNotContainsString('9:00', $row['context']);
        $this->assertSame(route('tasks.list', ['edit' => $task->id]), $row['destination']);
        $this->assertStringContainsString('ayer, 10 nov', $row['noticeDate']);
        $this->assertSame($before, $notice->fresh()->getAttributes());
        $this->travel(2)->days();
        $row = app(NotificationPresentation::class)->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertSame('danger', $row['tone']);
        $this->assertStringNotContainsString('mañana', $row['context']);
    }

    public function test_hour_completion_year_and_missing_or_foreign_sources(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Con hora', 'end_date' => '2026-11-11 15:00:00', 'end_is_date' => false]);
        $notice = $this->notice($user, $task, 'task_due', '2026-11-11 15:00:00');
        $presenter = app(NotificationPresentation::class);
        $row = $presenter->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertSame('warning', $row['tone']);
        $this->assertStringContainsString('15:00', $row['context']);
        // Deliberately bypass observers to characterize existing historical rows.
        DB::table('tasks')->where('id', $task->id)->update(['completed' => true]);
        $row = $presenter->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertSame('neutral', $row['tone']);
        $this->assertStringContainsString('Completada', $row['context']);
        $notice->occurrence_key = '2027-01-01 15:00:00';
        $this->assertStringContainsString('2027', $presenter->forNotices(collect([$notice]), $user->id)[$notice->id]['context']);
        DB::table('tasks')->where('id', $task->id)->update(['user_id' => User::factory()->create()->id]);
        $row = $presenter->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertSame('Elemento no disponible', $row['title']);
        $this->assertSame(route('tasks.list'), $row['destination']);
        $this->assertSame('neutral', $row['tone']);
        $this->assertSame([], $presenter->forNotices(collect([$notice]), $user->id + 999));
    }

    public function test_all_source_types_and_batched_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = Plan::create(['title' => 'Visitar museo', 'type' => 'museum', 'status' => 'scheduled', 'scheduled_on' => '2026-11-11']);
        $goal = Goal::create(['title' => 'Aprender francés', 'status' => 'active', 'due_date' => '2026-12-31']);
        $person = Relationship::create(['full_name' => 'Laura', 'category' => 'friend', 'birthday_month' => 11, 'birthday_day' => 11]);
        $planNotice = $this->notice($user, $plan, 'plan_due', '2026-11-11 09:00:00');
        $goalNotice = $this->notice($user, $goal, 'reminder', 'legacy');
        $birthday = $this->notice($user, $person, 'birthday', '2026-11-11');
        $notices = collect([$planNotice, $goalNotice, $birthday]);
        for ($i = 0; $i < 12; $i++) {
            $notices->push($this->notice($user, $plan, 'reminder', 'batch-'.$i));
        }
        $guardian = UserNotification::create(['user_id' => $user->id, 'source_type' => GuardianNudgePlanner::SOURCE, 'source_id' => GuardianNudgePlanner::sourceId('tasks'), 'type' => 'guardian_nudge', 'occurrence_key' => 'today', 'title' => 'Tu guardián', 'body' => 'Revisa tus tareas pendientes.', 'scheduled_for' => now()->subMinute()]);
        $summary = UserNotification::create(['user_id' => $user->id, 'source_type' => WeeklyInsights::SOURCE, 'source_id' => WeeklyInsights::sourceId(), 'type' => 'weekly_insights', 'occurrence_key' => '2026-11-09', 'title' => 'Tu resumen de la semana', 'body' => 'Tus avances.', 'scheduled_for' => now()->subMinute()]);
        $notices->push($guardian, $summary);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $rows = app(NotificationPresentation::class)->forNotices($notices, $user->id);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame('warning', $rows[$planNotice->id]['tone']);
        $this->assertSame('Aprender francés', $rows[$goalNotice->id]['title']);
        $this->assertStringContainsString('Fecha actual:', $rows[$goalNotice->id]['context']);
        $this->assertSame('Cumpleaños de Laura', $rows[$birthday->id]['title']);
        $this->assertSame('neutral', $rows[$birthday->id]['tone']);
        $this->assertStringContainsString('Tareas', $rows[$guardian->id]['context']);
        $this->assertSame('Revisa tus tareas pendientes.', $rows[$guardian->id]['body']);
        $this->assertSame(route('statistics').'#insights', $rows[$summary->id]['destination']);
        $this->assertStringContainsString('Semana del', $rows[$summary->id]['context']);
    }

    public function test_relative_dates_follow_month_year_and_local_midnight(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Cambio de año', 'end_date' => '2027-01-01', 'end_is_date' => true]);
        $notice = $this->notice($user, $task, 'task_due', '2027-01-01 09:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-12-31 23:59', 'America/Bogota'));
        $presenter = app(NotificationPresentation::class);
        $row = $presenter->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertStringContainsString('mañana, 1 ene.', $row['context']);
        $this->assertStringContainsString('2027', $row['context']);
        $this->travel(2)->minutes();
        $row = $presenter->forNotices(collect([$notice]), $user->id)[$notice->id];
        $this->assertStringContainsString('hoy, 1 ene.', $row['context']);
        $this->assertSame('warning', $row['tone']);
        $this->assertStringNotContainsString('2027', $row['context']);
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:59', 'America/Bogota'));
        $notice->occurrence_key = '2026-12-01 09:00:00';
        $this->assertStringContainsString('mañana, 1 dic.', $presenter->forNotices(collect([$notice]), $user->id)[$notice->id]['context']);
    }
}
