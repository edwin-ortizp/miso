<?php

namespace Tests\Feature;

use App\Livewire\Health\HealthBodyMap;
use App\Livewire\Health\HealthIndex;
use App\Livewire\Task\TaskGantt;
use App\Livewire\Task\TaskPlanning;
use App\Models\HealthEvent;
use App\Models\HealthLog;
use App\Models\Task;
use App\Models\TaskAssociation;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HealthModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::created(function (User $user) {
            DefaultTaskCategories::createFor($user);
            $user->taskCategories()->create(['key' => 'trabajo', 'name' => 'Trabajo']);
        });
    }

    public function test_future_medical_appointment_creates_a_linked_task_visible_in_task_views(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'appointment')
            ->set('title', 'Control de riñones')
            ->set('eventDate', '2026-08-15')
            ->set('provider', 'Nefrología Central')
            ->call('save')
            ->assertSet('showForm', false);

        $event = HealthEvent::firstOrFail();
        $task = Task::firstOrFail();
        $this->assertSame('appointment', $event->type);
        $this->assertSame('Nefrología Central', $event->details['provider']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'category' => 'salud', 'title' => 'Salud: Control de riñones', 'start_date' => '2026-08-15 00:00:00', 'end_date' => '2026-08-15 00:00:00']);
        $this->assertTrue($event->tasks()->whereKey($task->id)->exists());

        Livewire::test(TaskPlanning::class)->assertSee('Salud: Control de riñones');
        $this->assertContains('Salud: Control de riñones', array_column(Livewire::test(TaskGantt::class)->instance()->tasks(), 'name'));
        Carbon::setTestNow();
    }

    public function test_reprogramming_from_health_and_tasks_keeps_dates_synchronized(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $event = HealthEvent::create(['type' => 'checkup', 'title' => 'Examen de sangre', 'event_date' => '2026-08-01']);
        $task = Task::create(['title' => 'Salud: Examen de sangre', 'category' => 'salud', 'start_date' => '2026-08-01', 'end_date' => '2026-08-01']);
        TaskAssociation::link($task, $event);

        Livewire::test(HealthIndex::class)
            ->call('openForm', $event->id)
            ->set('eventDate', '2026-08-08')
            ->call('save');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'start_date' => '2026-08-08 00:00:00', 'end_date' => '2026-08-08 00:00:00']);

        $task->refresh()->update(['start_date' => '2026-08-20', 'end_date' => '2026-08-20']);
        $this->assertDatabaseHas('health_events', ['id' => $event->id, 'event_date' => '2026-08-20 00:00:00']);
        Carbon::setTestNow();
    }

    public function test_symptoms_validate_details_and_health_pending_is_only_a_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'symptom')
            ->set('title', 'Dolor de espalda')
            ->set('eventDate', '2026-07-12')
            ->set('bodyAreas', ['lower_back'])
            ->set('initialIntensity', 11)
            ->call('save')
            ->assertHasErrors(['initialIntensity' => 'between']);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'symptom')
            ->set('title', 'Dolor de espalda')
            ->set('eventDate', '2026-07-12')
            ->set('bodyAreas', ['lower_back'])
            ->set('initialIntensity', 3)
            ->call('save');

        $this->assertDatabaseHas('health_events', ['type' => 'symptom', 'title' => 'Dolor de espalda']);
        $this->assertSame(['lower_back'], HealthEvent::where('type', 'symptom')->firstOrFail()->details['body_areas']);
        $this->assertDatabaseHas('health_logs', ['intensity' => 3, 'date' => '2026-07-12 00:00:00']);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'illness')
            ->set('title', 'Malestar respiratorio')
            ->set('eventDate', '2026-07-12')
            ->set('illness', 'flu')
            ->set('initialIntensity', 5)
            ->call('save');

        $this->assertSame('flu', HealthEvent::where('type', 'illness')->firstOrFail()->details['condition']);

        Livewire::test(HealthIndex::class)
            ->call('openTaskForm')
            ->set('pendingTitle', 'Averiguar vacuna contra la fiebre amarilla')
            ->set('pendingDate', '2026-07-20')
            ->call('savePendingTask');

        $this->assertDatabaseCount('health_events', 2);
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Averiguar vacuna contra la fiebre amarilla', 'category' => 'salud']);
    }

    public function test_health_logs_track_daily_evolution_and_recovery(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'symptom')
            ->set('title', 'Dolor de garganta')
            ->set('eventDate', '2026-07-10')
            ->set('bodyAreas', ['mouth_throat'])
            ->set('initialIntensity', 3)
            ->call('save');

        $event = HealthEvent::firstOrFail();
        $initialLog = $event->logs()->firstOrFail();
        $this->assertSame(3, $initialLog->intensity);

        Livewire::test(HealthIndex::class)
            ->call('openLogForm', $event->id)
            ->set('logDate', '2026-07-11')
            ->set('logIntensity', 7)
            ->set('logNotes', 'Empeoró durante la noche')
            ->call('saveLog')
            ->assertSet('showLogForm', false)
            ->call('openLogForm', $event->id)
            ->set('logDate', '2026-07-09')
            ->set('logIntensity', 5)
            ->call('saveLog')
            ->assertHasErrors(['logDate'])
            ->call('editLog', $initialLog->id)
            ->set('logIntensity', 2)
            ->call('updateLog');

        $this->assertDatabaseHas('health_logs', ['id' => $initialLog->id, 'intensity' => 2]);
        $this->assertDatabaseCount('health_logs', 2);

        Livewire::test(HealthIndex::class)
            ->call('openRecoveryForm', $event->id)
            ->set('recoveryDate', '2026-07-12')
            ->set('recoveryIntensity', 1)
            ->call('saveRecovery');

        $this->assertSame('2026-07-12', $event->fresh()->end_date->toDateString());
        $this->assertDatabaseHas('health_logs', ['health_event_id' => $event->id, 'date' => '2026-07-12 00:00:00', 'intensity' => 1]);

        Livewire::test(HealthIndex::class)
            ->call('reopenEvolution', $event->id)
            ->call('openLogForm', $event->id)
            ->assertSet('showLogForm', true);
        $this->assertNull($event->fresh()->end_date);

        HealthLog::findOrFail($initialLog->id)->delete();
        $this->assertDatabaseCount('health_logs', 2);
        $event->delete();
        $this->assertDatabaseCount('health_logs', 0);
        Carbon::setTestNow();
    }

    public function test_health_events_are_private_to_their_owner_and_filters_are_url_backed(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->healthEvents()->create(['type' => 'vaccination', 'title' => 'Influenza', 'event_date' => '2026-06-01']);
        $owner->healthEvents()->create(['type' => 'checkup', 'title' => 'Control antiguo', 'event_date' => '2025-01-10']);
        $other->healthEvents()->create(['type' => 'symptom', 'title' => 'No visible', 'event_date' => '2026-06-01']);
        $this->actingAs($owner);

        Livewire::withQueryParams(['types' => ['vaccination'], 'range' => '60d'])
            ->test(HealthIndex::class)
            ->assertSet('types', ['vaccination'])
            ->assertSet('range', '60d')
            ->assertSee('Influenza')
            ->assertDontSee('Control antiguo')
            ->assertDontSee('No visible')
            ->assertSee('(1 / 2)');

        Carbon::setTestNow();
    }

    public function test_filters_can_be_removed_one_by_one_or_cleared_at_once(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HealthIndex::class)
            ->set('range', '30d')
            ->set('status', 'active')
            ->set('types', ['symptom', 'illness'])
            ->assertViewHas('activeFilters', fn (array $filters) => count($filters) === 4)
            ->assertSee('Limpiar filtros')
            ->call('removeFilter', 'types', 'illness')
            ->assertSet('types', ['symptom'])
            ->call('removeFilter', 'range')
            ->assertSet('range', 'all')
            ->call('clearFilters')
            ->assertSet('status', 'all')
            ->assertSet('types', [])
            ->assertViewHas('activeFilters', [])
            ->set('range', 'desconocido')
            ->assertSet('range', 'all')
            ->set('types', ['symptom', 'inventado'])
            ->assertSet('types', ['symptom']);
    }

    public function test_status_filter_distinguishes_active_and_recovered_evolutions(): void
    {
        $this->actingAs(User::factory()->create());
        HealthEvent::create(['type' => 'symptom', 'title' => 'Dolor activo', 'event_date' => '2026-06-01']);
        HealthEvent::create(['type' => 'illness', 'title' => 'Gripe superada', 'event_date' => '2026-05-01', 'end_date' => '2026-05-07']);
        HealthEvent::create(['type' => 'appointment', 'title' => 'Consulta dermatología', 'event_date' => '2026-06-02']);

        Livewire::test(HealthIndex::class)
            ->set('status', 'active')
            ->assertSee('Dolor activo')
            ->assertDontSee('Gripe superada')
            ->assertDontSee('Consulta dermatología')
            ->set('status', 'recovered')
            ->assertSee('Gripe superada')
            ->assertDontSee('Dolor activo');
    }

    public function test_illness_moments_count_symptoms_and_illnesses_per_month(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $this->actingAs(User::factory()->create());
        HealthEvent::create(['type' => 'symptom', 'title' => 'Migraña', 'event_date' => '2026-02-03']);
        HealthEvent::create(['type' => 'illness', 'title' => 'Gripe', 'event_date' => '2026-02-20']);
        HealthEvent::create(['type' => 'symptom', 'title' => 'Dolor lumbar', 'event_date' => '2026-05-01']);
        HealthEvent::create(['type' => 'appointment', 'title' => 'Cita', 'event_date' => '2026-05-02']);
        HealthEvent::create(['type' => 'symptom', 'title' => 'Tos', 'event_date' => '2025-12-01']);

        Livewire::test(HealthIndex::class)
            ->assertViewHas('moments', fn (array $moments) => $moments['total'] === 3
                && $moments['monthsWithEvents'] === 2
                && $moments['months'][1] === ['month' => 'Feb', 'count' => 2]
                && count($moments['months']) === 12
                && $moments['average'] === '0,3')
            ->set('illnessPeriod', 'last_year')
            ->assertViewHas('moments', fn (array $moments) => $moments['total'] === 1 && $moments['months'][11]['count'] === 1);

        Carbon::setTestNow();
    }

    public function test_pending_health_tasks_are_managed_from_their_menu(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Examen de sangre', 'category' => 'salud']);
        $work = Task::create(['title' => 'Informe trimestral', 'category' => 'trabajo']);

        $component = Livewire::test(HealthIndex::class)->call('completeTask', $task->id);
        $this->assertTrue($task->fresh()->completed);
        $component->assertViewHas('healthTasks', fn ($tasks) => $tasks->contains('title', 'Examen de sangre'));

        $component->call('reopenTask', $task->id);
        $this->assertFalse($task->fresh()->completed);

        $component->call('openRescheduleTask', $task->id)
            ->assertSet('showRescheduleForm', true)
            ->set('rescheduleDate', '2026-09-20')
            ->call('saveRescheduleTask')
            ->assertSet('showRescheduleForm', false);
        $this->assertSame('2026-09-20', $task->fresh()->start_date->toDateString());

        try {
            Livewire::test(HealthIndex::class)->call('deleteTask', $work->id);
            $this->fail('Una tarea que no es de salud no debe poder eliminarse desde Salud.');
        } catch (ModelNotFoundException) {
            $this->assertNotNull(Task::find($work->id));
        }

        $component->call('deleteTask', $task->id);
        $this->assertNull(Task::find($task->id));

        Carbon::setTestNow();
    }

    public function test_deleting_event_only_removes_its_link_not_the_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $event = $user->healthEvents()->create(['type' => 'checkup', 'title' => 'Control', 'event_date' => '2026-08-01']);
        $task = $user->tasks()->create(['title' => 'Salud: Control']);
        TaskAssociation::link($task, $event);

        Livewire::test(HealthIndex::class)->call('deleteEvent', $event->id);

        $this->assertDatabaseMissing('health_events', ['id' => $event->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_associations', ['task_id' => $task->id, 'target_id' => $event->id]);
    }

    public function test_body_map_counts_events_per_zone_with_filters_and_links_to_register(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['2026-07-01', '2026-06-01', '2026-05-01'] as $date) {
            $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor lumbar', 'event_date' => $date, 'details' => ['body_area' => 'lower_back']]);
        }
        $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor de cabeza', 'event_date' => '2026-07-11', 'end_date' => '2026-07-12', 'details' => ['body_area' => 'head']]);
        $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Esguince antiguo', 'event_date' => '2024-01-01', 'details' => ['body_area' => 'ankle_right']]);

        $this->get('/health/body')->assertOk()->assertSee('Mapa corporal')->assertSee('Zonas registradas')->assertSee('data-zone="knee_left"', false);

        Livewire::test(HealthBodyMap::class)
            ->assertSet('range', '1y')
            ->assertViewHas('total', 4)
            ->assertViewHas('zones', fn ($zones) => $zones->keys()->all() === ['lower_back', 'head']
                && $zones['lower_back']['level'] === 2
                && $zones['lower_back']['href'] === route('health', ['zone' => 'lower_back', 'range' => '1y']))
            ->assertViewHas('mapZones', fn ($zones) => $zones['head']['level'] === 1 && $zones['ankle_right']['count'] === 0 && ! isset($zones['skin']))
            ->set('status', 'recovered')
            ->assertViewHas('total', 1)
            ->call('removeFilter', 'range')
            ->assertSet('range', 'all')
            ->call('clearFilters')
            ->assertViewHas('total', 5)
            ->set('sort', 'name')
            ->assertViewHas('zones', fn ($zones) => $zones->keys()->first() === 'head');

        Livewire::withQueryParams(['zone' => 'lower_back'])
            ->test(HealthIndex::class)
            ->assertSet('zone', 'lower_back')
            ->assertViewHas('events', fn ($events) => $events->count() === 3)
            ->assertSee('Espalda baja')
            ->call('removeFilter', 'zone')
            ->assertSet('zone', '');

        Livewire::withQueryParams(['new_area' => 'knee_left'])
            ->test(HealthIndex::class)
            ->assertSet('showForm', true)
            ->assertSet('type', 'symptom')
            ->assertSet('bodyAreas', ['knee_left']);

        Carbon::setTestNow();
    }

    public function test_body_area_migration_converts_legacy_keys(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $back = $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Espalda', 'event_date' => '2026-07-01', 'details' => ['body_area' => 'back']]);
        $knees = $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Rodillas', 'event_date' => '2026-07-01', 'details' => ['body_area' => 'knees', 'severity' => 3]]);

        $migration = require database_path('migrations/2026_09_12_000001_migrate_health_body_areas.php');
        $migration->up();

        $this->assertSame(['body_area' => 'lower_back'], $back->fresh()->details);
        $this->assertSame(['body_area' => 'knee_right', 'severity' => 3, 'body_area_note' => 'Ambos lados (migrado)'], $knees->fresh()->details);

        $migration->down();
        $this->assertSame(['body_area' => 'knees', 'severity' => 3], $knees->fresh()->details);
    }

    public function test_illnesses_and_procedures_relate_to_several_body_areas_and_procedures_track_recovery(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'symptom')
            ->set('title', 'Molestia')
            ->set('eventDate', '2026-07-12')
            ->set('initialIntensity', 3)
            ->call('save')
            ->assertHasErrors(['bodyAreas' => 'required']);

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'illness')
            ->set('title', 'Gripe')
            ->set('eventDate', '2026-07-10')
            ->set('illness', 'flu')
            ->set('bodyAreas', ['head', 'mouth_throat', 'chest'])
            ->set('initialIntensity', 6)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'illness')
            ->set('title', 'Alergia')
            ->set('eventDate', '2026-07-11')
            ->set('illness', 'allergy')
            ->set('initialIntensity', 2)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'procedure')
            ->set('title', 'Cirugía de hernia')
            ->set('eventDate', '2026-07-08')
            ->set('provider', 'Dr. Ruiz')
            ->set('facility', 'Clínica Central')
            ->set('bodyAreas', ['lower_back', 'head'])
            ->call('save')
            ->assertHasNoErrors();

        $flu = HealthEvent::where('title', 'Gripe')->firstOrFail();
        $this->assertSame(['condition' => 'flu', 'body_areas' => ['head', 'mouth_throat', 'chest']], $flu->details);
        $this->assertArrayNotHasKey('body_areas', HealthEvent::where('title', 'Alergia')->firstOrFail()->details);

        $surgery = HealthEvent::where('title', 'Cirugía de hernia')->firstOrFail();
        $this->assertSame(['provider' => 'Dr. Ruiz', 'facility' => 'Clínica Central', 'body_areas' => ['lower_back', 'head']], $surgery->details);
        $this->assertSame(0, $surgery->logs()->count());

        Livewire::test(HealthIndex::class)
            ->call('openLogForm', $surgery->id)
            ->set('logDate', '2026-07-09')
            ->set('logIntensity', 5)
            ->call('saveLog')
            ->call('openRecoveryForm', $surgery->id)
            ->set('recoveryDate', '2026-07-12')
            ->set('recoveryIntensity', 1)
            ->call('saveRecovery');
        $this->assertSame('2026-07-12', $surgery->fresh()->end_date->toDateString());
        $this->assertSame(2, $surgery->logs()->count());

        Livewire::test(HealthIndex::class)->call('openForm', $surgery->id)
            ->assertSet('bodyAreas', ['lower_back', 'head'])
            ->assertSet('facility', 'Clínica Central');

        Livewire::test(HealthBodyMap::class)
            ->assertViewHas('total', 2)
            ->assertViewHas('zones', fn ($zones) => $zones['head']['count'] === 2 && $zones['mouth_throat']['count'] === 1 && $zones['lower_back']['count'] === 1)
            ->set('status', 'recovered')
            ->assertViewHas('total', 1);

        Livewire::withQueryParams(['zone' => 'head'])
            ->test(HealthIndex::class)
            ->assertViewHas('events', fn ($events) => $events->pluck('title')->sort()->values()->all() === ['Cirugía de hernia', 'Gripe']);

        $future = HealthEvent::create(['type' => 'procedure', 'title' => 'Cirugía programada', 'event_date' => '2026-08-01']);
        Livewire::test(HealthIndex::class)->call('openLogForm', $future->id)->assertStatus(404);

        Carbon::setTestNow();
    }

    public function test_genital_area_can_be_registered_and_is_drawn_on_the_body_map(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $this->actingAs(User::factory()->create());

        Livewire::test(HealthIndex::class)
            ->call('openForm')
            ->set('type', 'symptom')
            ->set('title', 'Irritación')
            ->set('eventDate', '2026-07-10')
            ->set('bodyAreas', ['genitals'])
            ->set('initialIntensity', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['genitals'], HealthEvent::firstOrFail()->bodyAreas());
        $this->get('/health/body')->assertOk()->assertSee('data-zone="genitals"', false);
        Livewire::test(HealthBodyMap::class)
            ->assertViewHas('mapZones', fn ($zones) => $zones['genitals']['count'] === 1 && $zones['genitals']['label'] === 'Zona genital');

        Carbon::setTestNow();
    }

    public function test_throat_and_face_are_drawn_on_the_body_map(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor de garganta', 'event_date' => '2026-07-10', 'details' => ['body_areas' => ['mouth_throat']]]);

        $this->get('/health/body')->assertOk()
            ->assertSee('data-zone="mouth_throat"', false)
            ->assertSee('data-zone="eyes_face"', false);
        Livewire::test(HealthBodyMap::class)
            ->assertViewHas('mapZones', fn ($zones) => $zones['mouth_throat']['count'] === 1 && $zones['eyes_face']['count'] === 0)
            ->assertViewHas('zones', fn ($zones) => $zones['mouth_throat']['onMap'] === true);

        Carbon::setTestNow();
    }

    public function test_multiple_body_areas_migration_and_legacy_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $legacy = $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor', 'event_date' => '2026-07-01', 'details' => ['body_area' => 'neck', 'severity' => 2]]);

        $this->assertSame(['neck'], $legacy->bodyAreas());
        Livewire::withQueryParams(['zone' => 'neck'])->test(HealthIndex::class)
            ->assertViewHas('events', fn ($events) => $events->count() === 1);

        $migration = require database_path('migrations/2026_09_13_000001_health_events_multiple_body_areas.php');
        $migration->up();
        $this->assertSame(['severity' => 2, 'body_areas' => ['neck']], $legacy->fresh()->details);

        $migration->down();
        $this->assertSame(['severity' => 2, 'body_area' => 'neck'], $legacy->fresh()->details);
    }

    public function test_health_context_reports_next_event_and_pending_tasks(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        HealthEvent::create(['type' => 'appointment', 'title' => 'Cita posterior', 'event_date' => '2026-08-10']);
        $next = HealthEvent::create(['type' => 'checkup', 'title' => 'Control cercano', 'event_date' => '2026-07-20']);
        Task::create(['title' => 'Pedir autorización', 'category' => 'salud']);

        Livewire::test(HealthIndex::class)
            ->assertViewHas('nextEvent', fn (?HealthEvent $event) => $event?->is($next))
            ->assertViewHas('upcomingCount', 2)
            ->assertViewHas('pendingHealthTasks', 1)
            ->assertViewHas('healthTasks', fn ($tasks) => $tasks->contains('title', 'Pedir autorización'))
            ->assertSee('Control cercano');

        Carbon::setTestNow();
    }
}
