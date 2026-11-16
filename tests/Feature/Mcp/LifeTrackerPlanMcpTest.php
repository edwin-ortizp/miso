<?php

namespace Tests\Feature\Mcp;

use App\Actions\RecordPlanVisit;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Plan\CreatePlanTool;
use App\Mcp\Tools\Plan\ListPlansTool;
use App\Mcp\Tools\Plan\ListPlanVisitsTool;
use App\Mcp\Tools\Plan\RecordPlanVisitTool;
use App\Mcp\Tools\Plan\UpdatePlanTool;
use App\Mcp\Tools\Relationship\CreateContactTool;
use App\Mcp\Tools\Relationship\ListCirclesTool;
use App\Models\Circle;
use App\Models\Plan;
use App\Models\PlanCategory;
use App\Models\PlanVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifeTrackerPlanMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_circles_returns_only_the_users_circles(): void
    {
        $user = User::factory()->create();
        Circle::factory()->for(User::factory()->create())->create(['name' => 'Círculo ajeno']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListCirclesTool::class, [])
            ->assertOk()
            ->assertSee('Pareja')
            ->assertSee('Trabajo')
            ->assertDontSee('Círculo ajeno');
    }

    public function test_create_plan_resolves_exact_circle_names_despite_similar_ones(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(CreatePlanTool::class, [
                'title' => 'Heladería Popsy',
                'type' => 'dessert',
                'city' => 'Popayán',
                'circle_names' => ['Familia', 'Amigos'],
                'links' => ['https://www.instagram.com/popsy'],
            ])
            ->assertOk()
            ->assertSee('Plan creado')
            ->assertSee('Heladería Popsy');

        $plan = Plan::withoutGlobalScopes()->where('user_id', $user->id)->with(['circles', 'links'])->sole();

        $this->assertEqualsCanonicalizing(['Familia', 'Amigos'], $plan->circles->pluck('name')->all());
        $this->assertSame('pending', $plan->status);
        $this->assertCount(1, $plan->links);
    }

    public function test_create_plan_links_contacts_and_schedules_dated_plans(): void
    {
        $user = User::factory()->create();
        $user->relationships()->create(['full_name' => 'Alison Pino', 'category' => 'pareja']);

        LifeTrackerServer::actingAs($user)
            ->tool(CreatePlanTool::class, [
                'title' => 'Concierto Morat',
                'type' => 'concert',
                'scheduled_on' => today()->addDays(60)->toDateString(),
                'contact_names' => ['Alison'],
            ])
            ->assertOk()
            ->assertSee('Alison Pino');

        $plan = Plan::withoutGlobalScopes()->where('user_id', $user->id)->with('relationships')->sole();
        $this->assertSame('scheduled', $plan->status);
        $this->assertSame(['Alison Pino'], $plan->relationships->pluck('full_name')->all());
    }

    public function test_plan_images_by_url_can_be_added_and_the_main_one_changed(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(CreatePlanTool::class, [
                'title' => 'Cartagena en pareja',
                'type' => 'trip',
                'image_urls' => ['https://example.com/playa.jpg'],
            ])
            ->assertOk();

        LifeTrackerServer::actingAs($user)
            ->tool(UpdatePlanTool::class, [
                'title' => 'Cartagena en pareja',
                'add_image_urls' => ['https://example.com/murallas.jpg'],
                'main_image_url' => 'https://example.com/murallas.jpg',
            ])
            ->assertOk();

        $plan = Plan::withoutGlobalScopes()->where('user_id', $user->id)->with('images')->sole();
        $this->assertSame(['https://example.com/murallas.jpg', 'https://example.com/playa.jpg'], $plan->images->pluck('url')->all());

        LifeTrackerServer::actingAs($user)
            ->tool(ListPlansTool::class, [])
            ->assertSee('murallas.jpg');
    }

    public function test_create_plan_rejects_unknown_circles_without_creating(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(CreatePlanTool::class, ['title' => 'Picnic', 'circle_names' => ['Vecinos del barrio']])
            ->assertHasErrors()
            ->assertSee('Vecinos del barrio');

        $this->assertSame(0, Plan::withoutGlobalScopes()->count());
    }

    public function test_create_plan_does_not_duplicate_an_existing_plan(): void
    {
        $user = User::factory()->create();
        Plan::factory()->for($user)->create(['title' => 'Museo Nacional', 'city' => 'Bogotá']);

        LifeTrackerServer::actingAs($user)
            ->tool(CreatePlanTool::class, ['title' => 'museo nacional', 'city' => 'Bogotá'])
            ->assertHasErrors()
            ->assertSee('update-plan-tool');

        $this->assertSame(1, Plan::withoutGlobalScopes()->count());
    }

    public function test_update_plan_adds_and_removes_circles(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->for($user)->create(['title' => 'Termales de Coconuco']);
        $plan->circles()->attach(Circle::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Familia')->value('id'));

        LifeTrackerServer::actingAs($user)
            ->tool(UpdatePlanTool::class, [
                'title' => 'Termales',
                'add_circle_names' => ['Pareja'],
                'remove_circle_names' => ['Familia'],
            ])
            ->assertOk()
            ->assertSee('Pareja');

        $this->assertSame(['Pareja'], $plan->circles()->withoutGlobalScopes()->pluck('name')->all());
    }

    public function test_list_plans_filters_by_circle(): void
    {
        $user = User::factory()->create();
        $pareja = Circle::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Pareja')->value('id');
        Plan::factory()->for($user)->create(['title' => 'Cena romántica'])->circles()->attach($pareja);
        Plan::factory()->for($user)->create(['title' => 'Asado de trabajo']);
        Plan::factory()->create(['title' => 'Plan ajeno']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListPlansTool::class, ['circle_name' => 'Pareja'])
            ->assertOk()
            ->assertSee('Cena romántica')
            ->assertDontSee('Asado de trabajo')
            ->assertDontSee('Plan ajeno');
    }

    public function test_contact_tools_resolve_an_exact_default_circle_name(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(CreateContactTool::class, ['full_name' => 'Julián Ortiz', 'circle_name' => 'Familia'])
            ->assertOk();

        $this->assertSame('Familia', $user->relationships()->sole()->circle()->withoutGlobalScopes()->value('name'));
    }

    public function test_record_visit_finds_the_plan_and_contact_by_alias(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $alison = $user->relationships()->create(['full_name' => 'Alison Pino', 'category' => 'pareja']);
        $alison->aliases()->create(['alias' => 'Ali']);
        $plan = Plan::factory()->for($user)->create(['title' => 'Heladería Popsy']);
        RecordPlanVisit::handle($plan, today()->subMonths(3)->toDateString(), [$alison->id]);

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, [
                'plan_title' => 'popsy',
                'contact_names' => ['Ali'],
                'visited_on' => today()->subDay()->toDateString(),
                'comment' => 'Probamos el de maracuyá.',
            ])
            ->assertOk()
            ->assertSee('Visita registrada')
            ->assertSee('Alison Pino')
            ->assertSee('2 visitas');

        $visit = PlanVisit::withoutGlobalScopes()->where('plan_id', $plan->id)->orderByDesc('visited_on')->with('relationships')->first();
        $this->assertSame(today()->subDay()->toDateString(), $visit->visited_on->toDateString());
        $this->assertSame('Probamos el de maracuyá.', $visit->comment);
        $this->assertSame([$alison->id], $visit->relationships->pluck('id')->all());
    }

    public function test_record_visit_defaults_to_today_and_rejects_future_dates(): void
    {
        $user = User::factory()->create();
        $user->relationships()->create(['full_name' => 'Julián Ortiz', 'category' => 'amigo']);
        Plan::factory()->for($user)->create(['title' => 'Ir al cine']);

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, ['plan_title' => 'Ir al cine', 'contact_names' => ['Julián'], 'visited_on' => today()->addDay()->toDateString()])
            ->assertHasErrors();

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, ['plan_title' => 'Ir al cine', 'contact_names' => ['Julián']])
            ->assertOk();

        $this->assertSame(today()->toDateString(), PlanVisit::withoutGlobalScopes()->sole()->visited_on->toDateString());
    }

    public function test_record_visit_rejects_unknown_contacts_without_saving(): void
    {
        $user = User::factory()->create();
        Plan::factory()->for($user)->create(['title' => 'Museo Nacional']);

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, ['plan_title' => 'Museo Nacional', 'contact_names' => ['Persona Inexistente']])
            ->assertHasErrors()
            ->assertSee('Persona Inexistente');

        $this->assertSame(0, PlanVisit::withoutGlobalScopes()->count());
    }

    public function test_record_visit_on_a_missing_plan_asks_or_creates_it(): void
    {
        $user = User::factory()->create();
        $user->relationships()->create(['full_name' => 'Alison Pino', 'category' => 'pareja']);

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, ['plan_title' => 'Restaurante Storia', 'contact_names' => ['Alison']])
            ->assertHasErrors()
            ->assertSee('create_if_missing');

        $this->assertSame(0, Plan::withoutGlobalScopes()->count());

        LifeTrackerServer::actingAs($user)
            ->tool(RecordPlanVisitTool::class, [
                'plan_title' => 'Restaurante Storia',
                'contact_names' => ['Alison'],
                'create_if_missing' => true,
                'type' => 'restaurant',
                'city' => 'Popayán',
                'circle_names' => ['Pareja'],
            ])
            ->assertOk()
            ->assertSee('Plan creado y visita registrada');

        $plan = Plan::withoutGlobalScopes()->where('user_id', $user->id)->with(['circles', 'visits'])->sole();
        $this->assertSame('restaurant', $plan->type);
        $this->assertSame(['Pareja'], $plan->circles->pluck('name')->all());
        $this->assertCount(1, $plan->visits);
    }

    public function test_record_visit_completes_a_scheduled_plan_and_skips_duplicates(): void
    {
        $user = User::factory()->create();
        $user->relationships()->create(['full_name' => 'Alison Pino', 'category' => 'pareja']);
        $plan = Plan::factory()->for($user)->scheduled(today()->toDateString())->create(['title' => 'Concierto Morat']);

        $payload = ['plan_id' => $plan->id, 'contact_names' => ['Alison']];

        LifeTrackerServer::actingAs($user)->tool(RecordPlanVisitTool::class, $payload)->assertOk();
        LifeTrackerServer::actingAs($user)->tool(RecordPlanVisitTool::class, $payload)->assertHasErrors()->assertSee('Ya estaba registrada');

        $this->assertSame('done', $plan->fresh()->status);
        $this->assertSame(1, PlanVisit::withoutGlobalScopes()->count());
    }

    public function test_list_plan_visits_filters_by_person_and_user(): void
    {
        $user = User::factory()->create();
        $alison = $user->relationships()->create(['full_name' => 'Alison Pino', 'category' => 'pareja']);
        $julian = $user->relationships()->create(['full_name' => 'Julián Ortiz', 'category' => 'amigo']);
        $popsy = Plan::factory()->for($user)->create(['title' => 'Heladería Popsy']);
        $cine = Plan::factory()->for($user)->create(['title' => 'Ir al cine']);

        $this->actingAs($user);
        RecordPlanVisit::handle($popsy, today()->subWeek()->toDateString(), [$alison->id], 'Con Ali');
        RecordPlanVisit::handle($cine, today()->subDays(2)->toDateString(), [$julian->id]);
        Plan::factory()->create(['title' => 'Plan ajeno']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListPlanVisitsTool::class, ['contact_name' => 'Alison'])
            ->assertOk()
            ->assertSee('Heladería Popsy')
            ->assertDontSee('Ir al cine')
            ->assertDontSee('Plan ajeno');
    }

    public function test_text_categories_remain_compatible_and_user_owned(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PlanCategory::forceCreate(['user_id' => $other->id, 'name' => 'Gastronomía', 'icon' => 'bi-grid']);
        LifeTrackerServer::actingAs($user)->tool(CreatePlanTool::class, ['title' => 'Cena MCP', 'category' => '  Gastronomía  '])->assertOk();
        $plan = Plan::withoutGlobalScopes()->where('user_id', $user->id)->sole();
        $category = PlanCategory::withoutGlobalScopes()->findOrFail($plan->plan_category_id);
        $this->assertSame($user->id, $category->user_id);
        $this->assertSame('Gastronomía', $plan->category);
        LifeTrackerServer::actingAs($user)->tool(UpdatePlanTool::class, ['plan_id' => $plan->id, 'category' => 'gastronomía'])->assertOk();
        $this->assertSame($category->id, $plan->fresh()->plan_category_id);
        LifeTrackerServer::actingAs($user)->tool(UpdatePlanTool::class, ['plan_id' => $plan->id, 'category' => 'Cultura'])->assertOk();
        $this->assertSame('Cultura', $plan->fresh()->category);
        $this->assertNotSame($category->id, $plan->fresh()->plan_category_id);
    }
}
