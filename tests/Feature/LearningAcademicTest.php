<?php

namespace Tests\Feature;

use App\Livewire\Learning\AcademicEditor;
use App\Livewire\Learning\StudyDashboard;
use App\Livewire\Learning\SubjectPanel;
use App\Livewire\Task\TaskCalendar;
use App\Livewire\Task\TaskEditor;
use App\Models\Task;
use App\Models\User;
use App\Services\Learning\AcademicRecords;
use App\Services\Learning\GradeProjection;
use App\Services\Learning\LearningRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LearningAcademicTest extends TestCase
{
    use RefreshDatabase;

    public function test_graded_task_is_created_once_and_academic_views_follow_the_real_task(): void
    {
        [$user, $unit] = $this->subject();
        $this->actingAs($user)->freezeTime();
        $deadline = today()->addDay()->toDateString();
        Livewire::test(AcademicEditor::class, ['context' => $unit->id])
            ->call('openTask')->set('draft.title', 'Entrega de Física')->set('draft.date', $deadline)
            ->set('graded', true)->set('draft.weight', '101')->call('save')->assertHasErrors('draft.weight');

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('learning_evaluations', 0);
        Livewire::test(AcademicEditor::class, ['context' => $unit->id])
            ->call('openTask')->set('draft.title', 'Entrega de Física')->set('draft.date', $deadline)
            ->set('graded', true)->set('draft.weight', '30')->call('save')
            ->assertHasNoErrors()->assertDispatched('study-academic-changed');

        $task = Task::sole();
        $this->assertTrue($task->end_is_date);
        $this->assertSame($deadline, $task->end_date->toDateString());
        $record = DB::table('learning_evaluations')->first();
        $this->assertSame($task->id, $record->task_id);
        $this->assertNull($record->scheduled_on);
        $this->assertNull($record->score);
        $this->get(route('learning.study'))->assertOk()->assertSee('Entrega de Física');
        $this->get(route('learning.units.show', [$unit->formation_id, $unit->id, 'section' => 'grades']))
            ->assertOk()->assertSee('Falta definir 70 %')->assertSee('Entrega de Física');

        $task->update(['title' => 'Entrega corregida']);
        $newDeadline = today()->addDays(2);
        Livewire::test(TaskEditor::class)->call('openScheduleForm', $task->id)
            ->set('endDate', $newDeadline->toDateString())->call('save')->assertHasNoErrors();
        $this->assertSame($newDeadline->toDateString(), $task->refresh()->end_date->toDateString());
        Livewire::test(StudyDashboard::class)->assertSee('Entrega corregida')->call('completeTask', $task->id)
            ->assertSee('Tarea completada.')->assertDontSee('Entrega corregida');
        $this->assertTrue($task->refresh()->completed);
        $this->assertNull(DB::table('learning_evaluations')->where('id', $record->id)->value('score'));

        Livewire::test(AcademicEditor::class, ['context' => $unit->id])->call('open', 'score', $record->id)
            ->set('draft.score', '0')->call('save')->assertHasNoErrors();
        $task->delete();
        $this->assertDatabaseHas('learning_evaluations', ['id' => $record->id, 'task_id' => null, 'score' => 0]);
        $this->assertDatabaseCount('learning_subject_tasks', 0);
    }

    public function test_projection_and_simulator_use_the_same_weights_without_saving_hypothetical_results(): void
    {
        [$user, $unit] = $this->subject();
        $this->actingAs($user);
        $records = app(AcademicRecords::class);
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $third = (string) Str::uuid();
        $records->saveEvaluation($user->id, $unit->id, $first, ['title' => 'Parcial 1', 'score' => '4,20', 'weight' => '30', 'kind' => 'exam']);
        $records->saveEvaluation($user->id, $unit->id, $second, ['title' => 'Parcial 2', 'weight' => '35', 'kind' => 'exam']);
        $records->saveEvaluation($user->id, $unit->id, $third, ['title' => 'Parcial 3', 'weight' => '35', 'kind' => 'exam']);
        Livewire::test(SubjectPanel::class, ['unitId' => $unit->id, 'section' => 'grades'])
            ->assertSee('Necesitas 2,49 para pasar')->assertSee('Simular notas');
        Livewire::test(AcademicEditor::class, ['context' => $unit->id])->call('open', 'simulate')
            ->set('scenario.'.$second, '8')->call('simulate')->assertHasErrors('scenario.'.$second)
            ->set('scenario.'.$second, '2,00')->call('simulate')->assertHasNoErrors()->assertSee('Necesitas 2,98 para pasar')
            ->set('scenario.'.$third, '3,00')->call('simulate')->assertSee('Nota simulada 3,01')->assertSee('Alcanza el mínimo')
            ->call('resetScenario')->assertSet('simulation', null)->assertSet('scenario.'.$second, '')->assertSet('scenario.'.$third, '')
            ->call('close')->assertSet('dialog', '');
        $this->assertDatabaseHas('learning_evaluations', ['id' => $second, 'score' => null]);
        $this->assertDatabaseHas('learning_evaluations', ['id' => $third, 'score' => null]);
    }

    public function test_cuts_and_exact_passing_boundary(): void
    {
        [$user, $unit] = $this->subject();
        $records = app(AcademicRecords::class);
        $records->saveBook($user->id, $unit->id, ['mode' => 'cuts', 'minimum' => 0, 'maximum' => 5, 'passing' => 3]);
        $projection = fn () => app(GradeProjection::class)->calculate(
            $records->book($user->id, $unit->id), $records->cuts($user->id, $unit->id), $records->evaluations($user->id, $unit->id)
        );
        $ids = [];
        foreach ([30, 30, 40] as $index => $weight) {
            $cut = (string) Str::uuid();
            $id = (string) Str::uuid();
            $ids[] = $id;
            $records->saveCut($user->id, $unit->id, $cut, ['title' => 'Corte '.($index + 1), 'weight' => $weight]);
            $records->saveEvaluation($user->id, $unit->id, $id, ['title' => 'Resultado', 'kind' => 'other',
                'cut_id' => $cut, 'weight' => 100, 'score' => $index === 2 ? null : '3']);
            $this->assertSame($index === 2 ? 'needed' : 'setup', $projection()['state']);
        }
        $this->assertSame(300, $projection()['value']);
        $records->recordScore($user->id, $unit->id, $ids[2], '2.99');
        $this->assertSame('failed', $projection()['state']);
        $this->assertTrue($projection()['rounding']);
        $records->recordScore($user->id, $unit->id, $ids[2], '3');
        $this->assertSame('passed', $projection()['state']);
        $records->recordScore($user->id, $unit->id, $ids[0], '0');
        $records->recordScore($user->id, $unit->id, $ids[1], '0');
        $records->recordScore($user->id, $unit->id, $ids[2], null);
        $this->assertSame('impossible', $projection()['state']);
        $this->assertEquals(200, $projection()['value']);
        $this->actingAs($user)->get(route('learning.study'))->assertSee('Con estas ponderaciones, el máximo posible es 2,00');
        $this->expectException(ValidationException::class);
        $records->saveCut($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Sobra', 'weight' => 1]);
    }

    public function test_calendar_layer_filters_range_and_owner_and_lists_linked_work_only_as_task(): void
    {
        [$user, $unit] = $this->subject();
        [$other, $foreign] = $this->subject();
        $records = app(AcademicRecords::class);
        $exam = (string) Str::uuid();
        $records->saveEvaluation($user->id, $unit->id, $exam, ['title' => 'Parcial público para su dueño', 'kind' => 'exam', 'date' => '2026-11-19', 'time' => '14:15']);
        $records->saveEvaluation($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Fuera del rango', 'kind' => 'exam', 'date' => '2026-11-22']);
        $records->saveEvaluation($other->id, $foreign->id, (string) Str::uuid(), ['title' => 'Parcial ajeno', 'kind' => 'exam', 'date' => '2026-11-19']);
        $task = $records->saveTask($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Entrega única', 'date' => '2026-11-20'], true);
        $records->saveTask($user->id, $unit->id, $task->id, ['title' => 'Reintento', 'date' => '2026-11-20'], true);
        $this->actingAs($user);

        $events = app(TaskCalendar::class)->events('2026-11-19', '2026-11-22', ['tasks', 'evaluations']);
        $this->assertCount(2, $events);
        $this->assertSame([$task->id, 'evaluation-'.$exam], array_column($events, 'id'));
        $this->assertSame('2026-11-19T14:15:00-05:00', $events[1]['start']);
        $this->assertStringContainsString('evaluation='.$exam, $events[1]['url']);
        $this->assertCount(1, app(TaskCalendar::class)->events('2026-11-19', '2026-11-22', ['tasks']));
        $this->get($events[1]['url'])->assertOk()->assertSee('learning-grade-row--selected');
        $this->get(route('learning.study'))->assertDontSee('Parcial ajeno');
        Livewire::test(AcademicEditor::class, ['context' => $unit->id])->call('openTask')
            ->set('unitId', $foreign->id)->set('draft.title', 'Intento ajeno')->call('save')->assertStatus(404);
    }

    public function test_week_and_undated_views_respect_academic_filters(): void
    {
        [$user, $unit] = $this->subject();
        [, $other] = $this->subject($user);
        $this->actingAs($user)->freezeTime();
        $records = app(AcademicRecords::class);
        $records->saveTask($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Sin agendar'], false);
        $records->saveTask($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Entrega atrasada', 'date' => today()->subDay()->toDateString()], false);
        $records->saveEvaluation($user->id, $unit->id, (string) Str::uuid(), ['title' => 'Parcial lejano', 'kind' => 'exam', 'date' => today()->addDays(10)->toDateString()]);
        $records->saveTask($user->id, $other->id, (string) Str::uuid(), ['title' => 'Otra formación', 'date' => today()->toDateString()], false);

        Livewire::test(StudyDashboard::class)->assertSee('Entrega atrasada')->assertSee('Vencida')->assertDontSee('Parcial lejano')
            ->set('formationFilter', $unit->formation_id)->set('semesterFilter', 'none')->assertDontSeeHtml('>Otra formación</button>')
            ->call('setWindow', 'undated')->assertSee('Sin agendar')->assertDontSee('Entrega atrasada')
            ->call('setWindow', 'all')->assertSee('Parcial lejano');
    }

    private function subject(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $records = app(LearningRecords::class);
        $formation = $records->saveFormation($user->id, null, ['title' => 'Ingeniería', 'type' => 'degree', 'status' => 'in_progress']);
        $unit = $records->saveUnit($user->id, $formation->id, null, null, 'subject', ['title' => 'Física', 'status' => 'in_progress']);

        return [$user, $unit];
    }
}
