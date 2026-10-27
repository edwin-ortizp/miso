<?php

namespace Tests\Feature;

use App\Livewire\Home\Panel\FocusTimer;
use App\Livewire\Home\WallPanel;
use App\Models\PomodoroSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelFocusSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(today()->setTime(20, 0));
        $this->actingAs(User::factory()->create());
    }

    public function test_focus_context_is_scoped_and_markdown_is_safe(): void
    {
        $task = \App\Models\Task::create(['title' => 'Reunión de revisión', 'description' => "## Agenda\n\n**Revisar acuerdos**\n\n<script>alert(1)</script>\n\n[Enlace](javascript:alert(1))"]);
        $component = Livewire::test(FocusTimer::class);
        $context = $component->instance()->focusTask($task->id);
        $this->assertSame($task->title, $context['title']);
        $this->assertStringContainsString('<strong>Revisar acuerdos</strong>', $context['html']);
        $this->assertStringNotContainsString('<script>', $context['html']);
        $this->assertStringNotContainsString('href="javascript:', $context['html']);
        $this->assertTrue($context['hasDescription']);
        $this->assertNull($component->instance()->focusTask('missing'));
        $this->assertNull($component->instance()->focusTask(null));
        $task->update(['description' => null]);
        $this->assertFalse($component->instance()->focusTask($task->id)['hasDescription']);
        $this->actingAs(User::factory()->create());
        $this->assertNull(Livewire::test(FocusTimer::class)->instance()->focusTask($task->id));
    }

    public function test_manual_session_saves_today_and_refreshes_panel(): void
    {
        $this->get('/panel')->assertSee('Registrar sesión de enfoque');
        Livewire::test(FocusTimer::class)->dispatch('panel-focus-manual')
            ->assertSet('showForm', true)
            ->set('selectedDate', '2020-01-01')
            ->set('manualStart', '08:30')->set('manualEnd', '10:00')
            ->set('manualDescription', '  Preparar entrega  ')
            ->assertSee('1 h 30 min')->call('saveManualSession')->assertHasNoErrors()
            ->assertSet('showForm', false)->assertDispatched('focus-session-saved')->assertDispatched('panel-changed');

        $session = PomodoroSession::sole();
        $this->assertEquals(auth()->id(), $session->user_id);
        $this->assertSame(today()->toDateString(), $session->date->toDateString());
        $this->assertEquals(5400, $session->duration);
        $this->assertTrue($session->completed);
        $this->assertNull($session->client_token);
        $this->assertSame('Preparar entrega', $session->description);
        Livewire::test(WallPanel::class)->assertViewHas('focusMinutes', 90);
        $this->actingAs(User::factory()->create());
        Livewire::test(WallPanel::class)->assertViewHas('focusMinutes', 0);
        $this->assertSame(0, PomodoroSession::count());
    }

    public function test_invalid_intervals_preserve_draft_without_saving(): void
    {
        foreach ([['', '', 'manualStart'], ['bad', '10:00', 'manualStart'], ['10:00', '09:00', 'manualEnd'], ['10:00', '10:00', 'manualEnd'], ['19:00', '21:00', 'manualEnd'], ['00:00', '17:00', 'manualEnd']] as [$start, $end, $field]) {
            Livewire::test(FocusTimer::class)->call('openManualForm')
                ->set('manualStart', $start)->set('manualEnd', $end)->set('manualDescription', 'Borrador')
                ->call('saveManualSession')->assertHasErrors($field)->assertSet('showForm', true)
                ->assertSet('manualDescription', 'Borrador')->assertNotDispatched('panel-changed');
        }
        $this->assertDatabaseCount('pomodoro_sessions', 0);
    }

    public function test_cancel_reopen_clears_errors_and_description_limit_is_enforced(): void
    {
        Livewire::test(FocusTimer::class)->call('openManualForm')
            ->set('manualStart', '08:00')->set('manualEnd', '09:00')
            ->set('manualDescription', str_repeat('á', 1001))
            ->call('saveManualSession')->assertHasErrors('manualDescription')
            ->call('closeForm')->assertSet('showForm', false)
            ->dispatch('panel-focus-manual')->assertHasNoErrors()
            ->assertSet('manualStart', '')->assertSet('manualEnd', '')->assertSet('manualDescription', '')
            ->set('manualStart', '08:00')->set('manualEnd', '09:00')->call('saveManualSession')->assertHasNoErrors();
        $this->assertNull(PomodoroSession::sole()->description);
    }

    public function test_date_is_resolved_at_save_and_client_cannot_select_an_edit(): void
    {
        $component = Livewire::test(FocusTimer::class)->call('openManualForm');
        $this->travelTo(today()->addDay()->setTime(12, 0));
        $component->set('editingId', 'untrusted-id')->set('editingDate', '2020-01-01')
            ->set('manualStart', '08:00')->set('manualEnd', '09:00')->call('saveManualSession')->assertHasNoErrors();
        $this->assertSame(today()->toDateString(), PomodoroSession::sole()->date->toDateString());
    }
}
