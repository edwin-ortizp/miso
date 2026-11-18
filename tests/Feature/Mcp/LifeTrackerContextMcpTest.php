<?php

namespace Tests\Feature\Mcp;

use App\Actions\RecordPlanVisit;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Context\GetHealthContextTool;
use App\Mcp\Tools\Context\GetPersonContextTool;
use App\Mcp\Tools\Context\GetRecentActivityTool;
use App\Mcp\Tools\Exercise\ListExerciseSessionsTool;
use App\Mcp\Tools\Habit\ListHabitsTool;
use App\Mcp\Tools\Health\ListHealthEventsTool;
use App\Mcp\Tools\Health\LogHealthEventTool;
use App\Mcp\Tools\Health\UpdateHealthEventTool;
use App\Mcp\Tools\Mood\ListEnergyEntriesTool;
use App\Mcp\Tools\Mood\ListMoodEntriesTool;
use App\Mcp\Tools\Plan\ListPlansTool;
use App\Mcp\Tools\Plan\ListPlanVisitsTool;
use App\Mcp\Tools\Relationship\ListContactsTool;
use App\Mcp\Tools\Relationship\ListRelationshipEventsTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\LinkTaskTool;
use App\Mcp\Tools\Vehicle\ListVehicleFillupsTool;
use App\Mcp\Tools\Water\ListWaterIntakeTool;
use App\Models\HabitAction;
use App\Models\HealthEvent;
use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\TaskAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Testing\TestResponse;
use ReflectionClass;
use Tests\TestCase;

class LifeTrackerContextMcpTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function data(TestResponse $response): array
    {
        return (fn () => $this->structuredContent())->call($response) ?? [];
    }

    /** Alison con alias "ali" y tres contactos cuyo nombre contiene "ali". */
    private function makeContacts(User $user): Relationship
    {
        $this->actingAs($user);
        $alison = $user->relationships()->create(['full_name' => 'Alison Pino', 'nickname' => 'Ali', 'category' => 'pareja']);
        $alison->aliases()->create(['alias' => 'amor']);
        $user->relationships()->create(['full_name' => 'Aliria Ramos', 'nickname' => 'Abuelita', 'category' => 'familia']);
        $user->relationships()->create(['full_name' => 'Natalia Montoya', 'category' => 'trabajo']);
        $user->relationships()->create(['full_name' => 'Angie Natalia Ortiz', 'nickname' => 'Natis', 'category' => 'amigo']);

        return $alison;
    }

    // ---- Servidor -------------------------------------------------------------

    public function test_server_instructions_only_reference_registered_tools(): void
    {
        $attribute = (new ReflectionClass(LifeTrackerServer::class))->getAttributes(Instructions::class)[0]->newInstance();
        $instructions = $attribute->value;

        $tools = collect((new ReflectionClass(LifeTrackerServer::class))->getDefaultProperties()['tools'])
            ->map(fn (string $class) => (new $class)->name());

        preg_match_all('/[a-z][a-z-]+-tool/', $instructions, $matches);

        $this->assertNotEmpty($matches[0]);
        foreach (array_unique($matches[0]) as $name) {
            $this->assertContains($name, $tools->all(), "Las instrucciones mencionan {$name}, que no está registrada.");
        }
        $this->assertStringContainsString('Cuándo consultar', $instructions);
    }

    // ---- Resolución de contactos ---------------------------------------------

    public function test_exact_nickname_wins_over_partial_matches(): void
    {
        $user = User::factory()->create();
        $alison = $this->makeContacts($user);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListContactsTool::class, ['name' => 'ali'])->assertOk());

        $this->assertCount(1, $data['contacts']);
        $this->assertSame($alison->id, $data['contacts'][0]['id']);
        $this->assertArrayNotHasKey('birthday', $data['contacts'][0], 'Los campos nulos se omiten.');
    }

    public function test_word_start_match_is_preferred_over_substring(): void
    {
        $user = User::factory()->create();
        $this->makeContacts($user);

        // "nat" empieza palabra en "Natalia Montoya", "Angie Natalia Ortiz" (y "Natis"); ninguno es exacto.
        $names = collect($this->data(LifeTrackerServer::actingAs($user)->tool(ListContactsTool::class, ['name' => 'nat']))['contacts'])->pluck('full_name');
        $this->assertEqualsCanonicalizing(['Natalia Montoya', 'Angie Natalia Ortiz'], $names->all());
    }

    public function test_plan_visits_resolve_alias_and_accept_contact_id(): void
    {
        $user = User::factory()->create();
        $alison = $this->makeContacts($user);
        $this->actingAs($user);
        $cine = Plan::factory()->for($user)->create(['title' => 'Ir al cine']);
        RecordPlanVisit::handle($cine, today()->subDays(2)->toDateString(), [$alison->id], 'Película');

        LifeTrackerServer::actingAs($user)->tool(ListPlanVisitsTool::class, ['contact_name' => 'Ali'])
            ->assertOk()->assertSee('Ir al cine');

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListPlanVisitsTool::class, ['contact_id' => $alison->id, 'since' => today()->subDay()->toDateString()]));
        $this->assertSame([], $data['visits']);
    }

    // ---- Planes y hábitos ----------------------------------------------------

    public function test_plans_flag_overdue_and_filter_never_visited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Plan::factory()->for($user)->create(['title' => 'Pollo Week', 'status' => 'scheduled', 'scheduled_on' => today()->subDays(10)]);
        $done = Plan::factory()->for($user)->create(['title' => 'Monserrate']);
        RecordPlanVisit::handle($done, today()->toDateString(), []);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListPlansTool::class, ['not_visited' => true]));

        $this->assertSame(['Pollo Week'], collect($data['plans'])->pluck('title')->all());
        $this->assertTrue($data['plans'][0]['is_overdue']);
    }

    public function test_habits_report_streak_and_hide_long_option_catalogs(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 12) as $i) {
            $user->exerciseTypes()->create(['name' => "Ejercicio {$i}", 'calories_per_hour' => 100, 'steps_equivalent' => 0]);
        }
        $habit = $user->habitDefinitions()->create(['name' => 'Ejercicio', 'time_of_day' => 'morning']);
        $action = new HabitAction(['habit_id' => $habit->id, 'action_key' => 'exercise.log', 'mode' => HabitAction::MODE_PROMPT, 'config' => []]);
        $action->user_id = $user->id;
        $action->save();
        foreach ([1, 2, 3] as $daysAgo) {
            $user->habitCompletions()->create(['habit_id' => $habit->id, 'date' => today()->subDays($daysAgo), 'completed' => true]);
        }

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHabitsTool::class));
        $item = $data['habits'][0];

        $this->assertFalse($item['completed']);
        $this->assertSame(3, $item['streak_days']);
        $this->assertSame(3, $item['last_7_days']);
        $this->assertStringContainsString('include_action_options', implode(' ', $item['action']['required_input']));

        $full = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHabitsTool::class, ['include_action_options' => true]));
        $this->assertStringContainsString('Ejercicio 12', implode(' ', $full['habits'][0]['action']['required_input']));
    }

    // ---- Salud ---------------------------------------------------------------

    private function makeHealthHistory(User $user): array
    {
        $this->actingAs($user);
        $renal = $user->healthEvents()->create(['type' => 'checkup', 'title' => 'Renograma', 'event_date' => '2026-04-17', 'notes' => 'Hidronefrosis bilateral, función renal preservada.']);
        $active = $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor en el costado', 'event_date' => today()->subDays(1), 'notes' => 'Zona renal con fiebre.', 'details' => ['body_areas' => ['lower_back']]]);
        $active->logs()->create(['date' => today()->subDay(), 'intensity' => 6]);
        $active->logs()->create(['date' => today(), 'intensity' => 4]);
        $old = $user->healthEvents()->create(['type' => 'illness', 'title' => 'Gripe', 'event_date' => '2026-09-02', 'end_date' => '2026-09-14']);
        $next = $user->healthEvents()->create(['type' => 'appointment', 'title' => 'Exámenes de sangre', 'event_date' => today()->addDays(5)]);
        $private = $user->healthEvents()->create(['type' => 'appointment', 'title' => 'Examen privado', 'event_date' => today()->subDays(3), 'is_sensitive' => true]);

        return compact('renal', 'active', 'old', 'next', 'private');
    }

    public function test_health_events_expose_status_and_hide_sensitive_by_default(): void
    {
        $user = User::factory()->create();
        $events = $this->makeHealthHistory($user);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHealthEventsTool::class)->assertOk());
        $byTitle = collect($data['events'])->keyBy('title');

        $this->assertSame(1, $data['hidden_sensitive']);
        $this->assertFalse($byTitle->has('Examen privado'));
        $this->assertSame('active', $byTitle['Dolor en el costado']['status']);
        $this->assertSame('recovered', $byTitle['Gripe']['status']);
        $this->assertSame('upcoming', $byTitle['Exámenes de sangre']['status']);
        $this->assertSame('done', $byTitle['Renograma']['status']);

        $active = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHealthEventsTool::class, ['active_only' => true, 'include_follow_ups' => true]));
        $this->assertSame(['Dolor en el costado'], collect($active['events'])->pluck('title')->all());
        $this->assertSame(4, $active['events'][0]['follow_ups'][0]['intensity']);

        $search = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHealthEventsTool::class, ['search' => 'riñón renal']));
        $this->assertEqualsCanonicalizing(['Renograma', 'Dolor en el costado'], collect($search['events'])->pluck('title')->all());

        $area = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHealthEventsTool::class, ['body_area' => 'lower_back']));
        $this->assertSame(['Dolor en el costado'], collect($area['events'])->pluck('title')->all());

        $withSensitive = $this->data(LifeTrackerServer::actingAs($user)->tool(ListHealthEventsTool::class, ['include_sensitive' => true]));
        $this->assertContains('Examen privado', collect($withSensitive['events'])->pluck('title')->all());
        $this->assertArrayNotHasKey('hidden_sensitive', $withSensitive);
    }

    public function test_health_events_can_be_logged_sensitive_and_updated(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)->tool(LogHealthEventTool::class, [
            'type' => 'appointment', 'title' => 'Consulta', 'event_date' => '2026-09-03', 'is_sensitive' => true,
        ])->assertOk();
        $event = HealthEvent::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($event->is_sensitive);

        LifeTrackerServer::actingAs($user)->tool(UpdateHealthEventTool::class, [
            'health_event_id' => $event->id, 'is_sensitive' => false, 'append_notes' => 'Resultados negativos.',
        ])->assertOk();
        $event->refresh();
        $this->assertFalse($event->is_sensitive);
        $this->assertSame('Resultados negativos.', $event->notes);

        LifeTrackerServer::actingAs($user)->tool(UpdateHealthEventTool::class, [
            'health_event_id' => $event->id, 'body_areas' => 'head',
        ])->assertHasErrors();

        $other = User::factory()->create();
        LifeTrackerServer::actingAs($other)->tool(UpdateHealthEventTool::class, [
            'health_event_id' => $event->id, 'title' => 'Ajeno',
        ])->assertHasErrors();
    }

    public function test_health_context_groups_active_upcoming_related_and_tasks(): void
    {
        $user = User::factory()->create();
        $events = $this->makeHealthHistory($user);
        $user->taskCategories()->create(['key' => 'salud', 'name' => 'Salud']);
        $call = $user->tasks()->create(['title' => 'Llamar para la ecografía', 'category' => 'salud']);
        $linked = $user->tasks()->create(['title' => 'Tomar muestra de orina']);
        $this->actingAs($user);
        TaskAssociation::link($linked, $events['active']);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(GetHealthContextTool::class, ['search' => 'renal riñón'])->assertOk());

        $this->assertSame('Dolor en el costado', $data['active'][0]['title']);
        $this->assertSame([4, 6], collect($data['active'][0]['follow_ups'])->pluck('intensity')->all());
        $this->assertSame('Tomar muestra de orina', $data['active'][0]['tasks'][0]['title']);
        $this->assertSame(['Exámenes de sangre'], collect($data['upcoming'])->pluck('title')->all());
        $this->assertSame(['Llamar para la ecografía'], collect($data['pending_tasks'])->pluck('title')->all());
        $this->assertSame(['Renograma'], collect($data['related_history'])->pluck('title')->all());
        $this->assertSame(1, $data['hidden_sensitive']);
        $this->assertStringNotContainsString('Examen privado', json_encode($data));
    }

    // ---- Personas ------------------------------------------------------------

    public function test_person_context_combines_visits_plans_events_and_tasks(): void
    {
        $user = User::factory()->create();
        $alison = $this->makeContacts($user);
        $this->actingAs($user);
        $pareja = $user->circles()->create(['name' => 'Pareja']);
        $alison->update(['circle_id' => $pareja->id, 'birthday_month' => 1, 'birthday_day' => 7]);

        $cine = Plan::factory()->for($user)->create(['title' => 'Ir al cine']);
        RecordPlanVisit::handle($cine, today()->subDays(3)->toDateString(), [$alison->id]);
        RecordPlanVisit::handle($cine, today()->subDays(10)->toDateString(), [$alison->id]);
        Plan::factory()->for($user)->create(['title' => 'Glamping', 'status' => 'pending'])->circles()->attach($pareja);

        RelationshipEvent::factory()->for($alison)->create(['user_id' => $user->id, 'title' => 'Empezó trabajo nuevo', 'category' => 'education-work']);
        RelationshipEvent::factory()->for($alison)->create(['user_id' => $user->id, 'title' => 'Tema privado', 'is_sensitive' => true]);

        $user->tasks()->create(['title' => 'Comprar regalo para Ali']);
        $user->tasks()->create(['title' => 'Plan de alimentación']); // "ali" dentro de otra palabra no cuenta.

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(GetPersonContextTool::class, ['name' => 'ali'])->assertOk());

        $this->assertSame('Alison Pino', $data['person']['name']);
        $this->assertSame('Pareja', $data['person']['circle']);
        $this->assertArrayHasKey('birthday_in_days', $data['person']);
        $this->assertSame(2, $data['visits']['total']);
        $this->assertSame('Ir al cine', $data['visits']['most_repeated'][0]['plan']);
        // Lo nunca hecho juntos va primero; los planes repetibles ya visitados quedan al final.
        $this->assertSame(['Glamping', 'Ir al cine'], collect($data['pending_plans']['items'])->pluck('title')->all());
        $this->assertSame(1, $data['pending_plans']['never_done_together']);
        $this->assertSame(['Empezó trabajo nuevo'], collect($data['life_events']['items'])->pluck('title')->all());
        $this->assertSame(1, $data['life_events']['hidden_sensitive']);
        $this->assertSame(['Comprar regalo para Ali'], collect($data['tasks'])->pluck('title')->all());
    }

    public function test_relationship_events_list_filters_by_person_and_hides_sensitive(): void
    {
        $user = User::factory()->create();
        $alison = $this->makeContacts($user);
        RelationshipEvent::factory()->for($alison)->create(['user_id' => $user->id, 'title' => 'Viaje a Pasto', 'category' => 'travel']);
        RelationshipEvent::factory()->for($alison)->create(['user_id' => $user->id, 'title' => 'Privado', 'is_sensitive' => true]);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(ListRelationshipEventsTool::class, ['name' => 'Ali']));

        $this->assertSame(['Viaje a Pasto'], collect($data['events'])->pluck('title')->all());
        $this->assertSame(1, $data['hidden_sensitive']);
    }

    // ---- Bienestar y vehículos -------------------------------------------------

    public function test_mood_and_energy_lists_summarize_the_period(): void
    {
        $user = User::factory()->create();
        $happy = MoodState::factory()->create(['user_id' => $user->id, 'text' => 'Feliz', 'emoji' => '🙂', 'value' => 4]);
        MoodEntry::factory()->create(['user_id' => $user->id, 'mood_state_id' => $happy->id, 'date' => today()->subDays(2)]);
        MoodEntry::factory()->create(['user_id' => $user->id, 'mood_state_id' => $happy->id, 'date' => today()->subDays(40)]);
        $user->energyEntries()->create(['date' => today(), 'level' => 2, 'time' => '08:00', 'timestamp' => now()->timestamp]);
        $user->energyEntries()->create(['date' => today()->subDay(), 'level' => 4, 'time' => '08:00', 'timestamp' => now()->timestamp]);

        $mood = $this->data(LifeTrackerServer::actingAs($user)->tool(ListMoodEntriesTool::class));
        $this->assertSame(1, $mood['summary']['entries']);
        $this->assertSame('🙂 Feliz', $mood['summary']['top_emotions'][0]['emotion']);

        $energy = $this->data(LifeTrackerServer::actingAs($user)->tool(ListEnergyEntriesTool::class));
        $this->assertEquals(3, $energy['summary']['average_level']);
        $this->assertSame(today()->toDateString(), $energy['summary']['lowest_day']);
    }

    public function test_exercise_water_and_fillup_lists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $run = $user->exerciseTypes()->create(['name' => 'Trotar', 'calories_per_hour' => 600, 'steps_equivalent' => 0]);
        $user->exerciseLogs()->create(['date' => today()->subDays(2), 'exercise_type_id' => $run->id, 'duration' => 30, 'distance' => 4]);
        $user->exerciseLogs()->create(['date' => today()->subDays(1), 'exercise_type_id' => $run->id, 'duration' => 20]);
        $water = $user->drinkTypes()->create(['name' => 'Agua', 'hydration_factor' => 1]);
        $user->drinkLogs()->create(['date' => today(), 'drink_type_id' => $water->id, 'drink_type' => 'Agua', 'amount' => 500, 'hydration_value' => 500, 'time' => '09:00', 'timestamp' => now()->timestamp]);
        $vehicle = $user->vehicles()->create(['name' => 'Cruze', 'vehicle_type' => 'car', 'power_source' => 'gasolina', 'usage_unit' => 'km', 'fuel_volume_unit' => 'gal']);
        $vehicle->energyLogs()->create(['recorded_on' => today()->subDays(4), 'energy_source' => 'gasolina', 'quantity' => 10, 'unit' => 'gal', 'is_full' => true, 'cost' => 160000, 'usage_reading' => 142000]);

        $exercise = $this->data(LifeTrackerServer::actingAs($user)->tool(ListExerciseSessionsTool::class));
        $this->assertSame(2, $exercise['summary']['sessions']);
        $this->assertSame(50, $exercise['summary']['minutes']);
        $this->assertSame('Trotar', $exercise['by_type'][0]['type']);
        $this->assertArrayNotHasKey('pace_seconds_per_km', $exercise['sessions'][0]);
        $this->assertSame(450, $exercise['sessions'][1]['pace_seconds_per_km']);

        $hydration = $this->data(LifeTrackerServer::actingAs($user)->tool(ListWaterIntakeTool::class, ['days' => 3]));
        $this->assertCount(3, $hydration['by_day']);
        $this->assertSame(500, $hydration['by_day'][0]['hydration_ml']);

        $fillups = $this->data(LifeTrackerServer::actingAs($user)->tool(ListVehicleFillupsTool::class)->assertOk());
        $this->assertEquals(16000, $fillups['fillups'][0]['unit_price']);
    }

    public function test_recent_activity_builds_a_cross_module_timeline(): void
    {
        $user = User::factory()->create();
        $alison = $this->makeContacts($user);
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Entregar informe']);
        $task->forceFill(['completed' => true, 'completed_at' => now()])->save();
        RecordPlanVisit::handle(Plan::factory()->for($user)->create(['title' => 'Monserrate']), today()->subDay()->toDateString(), [$alison->id]);
        $user->healthEvents()->create(['type' => 'symptom', 'title' => 'Dolor en el costado', 'event_date' => today()]);
        $user->healthEvents()->create(['type' => 'appointment', 'title' => 'Algo privado', 'event_date' => today(), 'is_sensitive' => true]);

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(GetRecentActivityTool::class)->assertOk());
        $text = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Completó \"Entregar informe\"', $text);
        $this->assertMatchesRegularExpression('/Monserrate[^"]* con Ali/u', $text);
        $this->assertArrayNotHasKey('exercise', $data['summary'], 'Un módulo sin registros no ocupa espacio.');
        $this->assertStringContainsString('Dolor en el costado', $text);
        $this->assertStringNotContainsString('Algo privado', $text);
        $this->assertSame(1, $data['summary']['tasks']['completed']);

        $onlyPlans = $this->data(LifeTrackerServer::actingAs($user)->tool(GetRecentActivityTool::class, ['modules' => ['plans']]));
        $this->assertSame(['plans'], array_keys($onlyPlans['summary']));
    }

    // ---- Vínculos de tareas ----------------------------------------------------

    public function test_tasks_can_be_linked_to_people_and_health_events(): void
    {
        $user = User::factory()->create();
        $this->makeContacts($user);
        $event = $user->healthEvents()->create(['type' => 'appointment', 'title' => 'Cita medicina general', 'event_date' => today()]);
        $task = $user->tasks()->create(['title' => 'Pedir cita ecografía']);

        LifeTrackerServer::actingAs($user)->tool(LinkTaskTool::class, ['task_id' => $task->id, 'target_type' => 'health_event', 'target_id' => $event->id])->assertOk();
        LifeTrackerServer::actingAs($user)->tool(LinkTaskTool::class, ['task_id' => $task->id, 'target_type' => 'contact', 'contact_name' => 'Ali'])->assertOk();

        $data = $this->data(LifeTrackerServer::actingAs($user)->tool(GetTaskTool::class, ['task_id' => $task->id]));
        $this->assertSame('Cita medicina general', $data['linked']['health_events'][0]['title']);
        $this->assertSame('Ali', $data['linked']['contacts'][0]['name']);

        LifeTrackerServer::actingAs($user)->tool(LinkTaskTool::class, ['task_id' => $task->id, 'target_type' => 'contact', 'contact_name' => 'Ali', 'unlink' => true])
            ->assertOk()->assertSee('desvinculada');

        $foreign = User::factory()->create()->healthEvents()->create(['type' => 'checkup', 'title' => 'Ajeno', 'event_date' => today()]);
        LifeTrackerServer::actingAs($user)->tool(LinkTaskTool::class, ['task_id' => $task->id, 'target_type' => 'health_event', 'target_id' => $foreign->id])
            ->assertHasErrors();
    }
}
