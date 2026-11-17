<?php

namespace Tests\Feature;

use App\Livewire\Learning\LearningPage;
use App\Livewire\Learning\LearningSettings;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Learning\LearningRecords;
use App\Services\Learning\LearningVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-141: vocabulario de Formación según el tipo de estudio. */
class LearningVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private function vocab(): LearningVocabulary
    {
        return new LearningVocabulary;
    }

    public function test_defaults_use_colombian_terms_by_formation_type(): void
    {
        $user = User::factory()->create();
        $vocab = $this->vocab();

        foreach (['degree', 'specialization', 'masters', 'doctorate'] as $type) {
            $this->assertSame('semestre', $vocab->singular($user->id, $type, 'period'), $type);
            $this->assertSame('materias', $vocab->plural($user->id, $type, 'subject'), $type);
        }
        $this->assertSame('módulo', $vocab->singular($user->id, 'course', 'module'));
        $this->assertSame([], array_intersect(['period'], $vocab->kinds('course')), 'Un curso no tiene semestres.');
        $this->assertSame('etapa', $vocab->singular($user->id, 'personal', 'stage'));
        $this->assertSame('Pregrado', $vocab->typeLabel('degree'));
        $this->assertSame('Semestre', $vocab->label($user->id, 'degree', 'period'));
        $this->assertSame('una materia', $vocab->indefinite($user->id, 'degree', 'subject'));
        $this->assertSame('Este semestre', $vocab->demonstrative($user->id, 'degree', 'period'));
        $this->assertSame('las materias', $vocab->definitePlural($user->id, 'degree', 'subject'));
        $this->assertSame('Agregar semestre', $vocab->addLabel($user->id, 'degree', 'period'));
        $this->assertSame('semestres y materias', $vocab->plurals($user->id, 'degree', ['period', 'subject']));
        $this->assertSame('etapas, cursos y temas', $vocab->plurals($user->id, 'personal', ['stage', 'course', 'topic']));
    }

    public function test_a_custom_term_replaces_the_default_with_the_right_gender_and_is_isolated_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $vocab = $this->vocab();

        $vocab->save($a->id, ['masters' => ['subject' => ['singular' => 'Asignatura', 'plural' => 'asignaturas'], 'period' => ['singular' => 'ciclo', 'plural' => 'ciclos']]]);

        $fresh = $this->vocab();
        $this->assertSame('asignatura', $fresh->singular($a->id, 'masters', 'subject'));
        $this->assertSame('una asignatura', $fresh->indefinite($a->id, 'masters', 'subject'));
        $this->assertSame('un ciclo', $fresh->indefinite($a->id, 'masters', 'period'));
        $this->assertSame('materia', $fresh->singular($a->id, 'degree', 'subject'), 'Solo cambia el tipo personalizado.');
        $this->assertSame('materia', $fresh->singular($b->id, 'masters', 'subject'));
        $this->assertSame(['masters'], array_keys(ModuleSetting::withoutGlobalScopes()->where('user_id', $a->id)->where('module', 'learning')->first()->settings['vocabulary']));

        $fresh->reset($a->id);
        $this->assertSame('materia', $this->vocab()->singular($a->id, 'masters', 'subject'));
    }

    public function test_saving_validates_length_markup_and_duplicates_and_keeps_other_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ModuleSetting::create(['module' => 'learning', 'settings' => ['other' => 'keep']]);
        $vocab = $this->vocab();

        foreach ([
            ['degree' => ['subject' => ['singular' => str_repeat('a', 31)]]],
            ['degree' => ['subject' => ['singular' => '<b>x</b>']]],
            ['degree' => ['subject' => ['singular' => 'semestre']]],
        ] as $input) {
            try {
                $vocab->save($user->id, $input);
                $this->fail('Se esperaba un error de validación.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $vocab->save($user->id, ['degree' => ['subject' => ['singular' => 'curso', 'plural' => 'cursos']]]);
        $settings = ModuleSetting::withoutGlobalScopes()->where('user_id', $user->id)->where('module', 'learning')->first()->settings;
        $this->assertSame('keep', $settings['other']);
        $this->assertSame('curso', $settings['vocabulary']['degree']['subject']['singular']);
    }

    public function test_guessing_gender_handles_common_words(): void
    {
        foreach (['materia' => 'f', 'asignatura' => 'f', 'unidad' => 'f', 'lección' => 'f', 'semestre' => 'm', 'curso' => 'm', 'tema' => 'm', 'módulo' => 'm', 'trimestre' => 'm'] as $word => $gender) {
            $this->assertSame($gender, LearningVocabulary::guessGender($word), $word);
        }
    }

    public function test_the_page_speaks_each_formation_type_in_its_own_words(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(LearningRecords::class);
        $degree = $service->saveFormation($user->id, null, ['title' => 'Mi carrera', 'type' => 'degree', 'status' => 'in_progress']);
        $course = $service->saveFormation($user->id, null, ['title' => 'Curso de Laravel', 'type' => 'course', 'status' => 'in_progress']);

        $this->get(route('learning'))->assertOk()->assertSee('Pregrado')->assertSee('Agregar formación');
        Livewire::test(LearningPage::class, ['formation' => $degree->id])
            ->assertSee('Agregar semestre')->assertSee('Agregar materia')->assertSee('Agrega semestres y materias')
            ->call('openUnit', 'period')->assertSee('Agregar semestre')->assertDontSee('Agregar periodo');
        Livewire::test(LearningPage::class, ['formation' => $course->id])
            ->assertSee('Agregar módulo')->assertDontSee('Agregar semestre')->assertSee('Agrega módulos');
    }

    public function test_the_credit_error_and_labels_follow_the_custom_vocabulary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->vocab()->save($user->id, ['degree' => ['subject' => ['singular' => 'asignatura', 'plural' => 'asignaturas']]]);
        $service = app(LearningRecords::class);
        $formation = $service->saveFormation($user->id, null, ['title' => 'Carrera', 'type' => 'degree', 'status' => 'pending']);
        $period = $service->saveUnit($user->id, $formation->id, null, null, 'period', ['title' => 'Primero', 'status' => 'pending']);

        try {
            $service->saveUnit($user->id, $formation->id, null, $period->id, 'subject', ['title' => 'Álgebra', 'status' => 'pending', 'credits' => '3']);
            $service->saveUnit($user->id, $formation->id, null, null, 'period', ['title' => 'Segundo', 'status' => 'pending', 'credits' => '3']);
            $this->fail('Se esperaba un error.');
        } catch (ValidationException $e) {
            $this->assertSame(['credits'], array_keys($e->errors()), json_encode($e->errors()));
            $this->assertSame('Solo las asignaturas admiten créditos.', $e->errors()['credits'][0]);
        }
        Livewire::test(LearningPage::class, ['formation' => $formation->id])->assertSee('Agregar asignatura');
        Livewire::test(LearningPage::class, ['formation' => $formation->id, 'unit' => $period->id])->assertSee('Asignatura')->assertSee('Álgebra')->assertSee('Agregar asignatura');
    }

    public function test_settings_screen_edits_validates_and_restores(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('learning.settings'))->assertOk()->assertSee('Vocabulario')->assertSee('Pregrado')->assertSee('Vista previa: Agregar semestre · Agregar materia · Agregar tema');

        Livewire::test(LearningSettings::class)
            ->assertSet('vocabulary.degree.period.singular', 'semestre')
            ->set('vocabulary.degree.period.singular', 'ciclo')
            ->set('vocabulary.degree.period.plural', 'ciclos')
            ->call('save')->assertHasNoErrors()->assertSet('message', 'Vocabulario guardado.')
            ->set('vocabulary.degree.subject.singular', str_repeat('x', 40))->call('save')->assertHasErrors('vocabulary.degree.subject.singular')
            ->set('vocabulary.degree.subject.singular', 'materia')
            ->call('restore')->assertSet('vocabulary.degree.period.singular', 'semestre');
        $this->assertSame('semestre', $this->vocab()->singular($user->id, 'degree', 'period'));
    }
}
