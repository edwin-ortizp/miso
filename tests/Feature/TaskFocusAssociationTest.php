<?php

namespace Tests\Feature;

use App\Livewire\Pomodoro\PomodoroTimer;
use App\Models\PomodoroSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskFocusAssociationTest extends TestCase
{
    use RefreshDatabase;

    public function test_focus_records_owner_task_once_and_keeps_snapshot_after_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Estudiar']);
        $token = (string) str()->uuid();
        $end = now()->timestamp;
        PomodoroTimer::storeTimerSession($end - 600, $end, 'Notas', $token, $task->id);
        PomodoroTimer::storeTimerSession($end - 600, $end, 'Notas', $token, $task->id);
        $session = PomodoroSession::firstOrFail();
        $this->assertSame($task->id, $session->task_id);
        $this->assertSame(1, PomodoroSession::count());
        $task->delete();
        $this->assertNull($session->fresh()->task_id);
        $this->assertSame('Estudiar', $session->fresh()->task_title);
    }

    public function test_existing_timer_token_cannot_be_reassociated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $first = $user->tasks()->create(['title' => 'A']);
        $second = $user->tasks()->create(['title' => 'B']);
        $token = (string) str()->uuid();
        $end = now()->timestamp;
        PomodoroTimer::storeTimerSession($end - 600, $end, '', $token, $first->id);
        $this->expectException(ValidationException::class);
        PomodoroTimer::storeTimerSession($end - 600, $end, '', $token, $second->id);
    }
    public function test_timer_rejects_another_users_task_and_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner);
        $task = $owner->tasks()->create(['title' => 'Propia']);
        $token = (string) str()->uuid();
        $end = now()->timestamp;
        PomodoroTimer::storeTimerSession($end - 600, $end, '', $token, $task->id);
        $this->actingAs($other);
        try {
            PomodoroTimer::storeTimerSession($end - 600, $end, '', $token);
            $this->fail('El token ajeno debe rechazarse.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $this->assertSame(1, PomodoroSession::withoutGlobalScopes()->count());
        }
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        PomodoroTimer::storeTimerSession($end - 600, $end, '', (string) str()->uuid(), $task->id);
    }
}
