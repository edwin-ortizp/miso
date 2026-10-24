<?php

namespace Tests\Feature;

use App\Actions\SavePlan;
use App\Livewire\Plan\PlanHistory;
use App\Livewire\Plan\PlanIndex;
use App\Livewire\Plan\PlanScheduled;
use App\Livewire\Plan\PlanSettings;
use App\Livewire\Plan\PlanShow;
use App\Livewire\Relationship\RelationshipPlans;
use App\Models\Circle;
use App\Models\Plan;
use App\Models\PlanCategory;
use App\Models\PlanTag;
use App\Models\PlanVisit;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlanModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_routes_render_inside_the_shell(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->for($user)->create(['title' => 'Heladería Popsy']);
        $person = Relationship::factory()->for($user)->create();

        foreach (['/plans', '/plans/scheduled', '/plans/history', '/plans/settings', "/plans/{$plan->id}", "/relationships/{$person->id}/plans"] as $url) {
            $response = $this->actingAs($user)->get($url)->assertOk()->assertSee('md-module-shell', false);
            $this->assertSame(1, substr_count($response->getContent(), '<h1'), "{$url} should render one module heading.");
        }
    }

    public function test_plan_tags_can_be_managed_but_in_use_tags_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(PlanSettings::class)
            ->call('openForm')
            ->set('name', 'Sushi')
            ->set('icon', 'bi-egg-fried')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(PlanSettings::class)
            ->call('openForm')
            ->set('name', 'Sushi')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $tag = PlanTag::query()->sole();
        $plan = Plan::factory()->for($user)->create();
        $plan->syncTags([$tag->id]);

        Livewire::test(PlanSettings::class)
            ->call('delete', $tag->id)
            ->assertSee('está asociada a 1 plan');

        $this->assertDatabaseHas('plan_tags', ['id' => $tag->id]);
    }

    public function test_plan_tags_are_assignable_filterable_and_scoped_to_the_owner(): void
    {
        $user = User::factory()->create();
        $ownTag = PlanTag::forceCreate(['user_id' => $user->id, 'name' => 'Sushi', 'icon' => 'bi-egg-fried']);
        $foreignTag = PlanTag::forceCreate(['user_id' => User::factory()->create()->id, 'name' => 'Secreto']);
        $this->actingAs($user);

        Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Restaurante japonés')
            ->set('planTags', [$ownTag->id, $foreignTag->id])
            ->call('savePlan')
            ->assertHasErrors(['planTags.1']);

        Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Restaurante japonés')
            ->set('planTags', [$ownTag->id])
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::query()->with('tags')->sole();
        $this->assertSame(['Sushi'], $plan->tags->pluck('name')->all());
        $this->get("/plans/{$plan->id}")->assertSee('Sushi');

        $secondTag = PlanTag::forceCreate(['user_id' => $user->id, 'name' => 'Ramen']);
        Livewire::test(PlanIndex::class)
            ->call('openPlanForm', $plan->id)
            ->set('planTags', [$ownTag->id, $secondTag->id])
            ->call('savePlan')
            ->assertHasNoErrors();
        $this->assertEqualsCanonicalizing(['Sushi', 'Ramen'], $plan->fresh()->tags->pluck('name')->all());

        Plan::factory()->for($user)->create(['title' => 'Pizzería']);
        Livewire::test(PlanIndex::class)
            ->set('tags', [$ownTag->id])
            ->assertSee('Restaurante japonés')
            ->assertDontSee('Pizzería');

        $plan->delete();
        $this->assertDatabaseMissing('plan_tag_assignments', ['plan_id' => $plan->id]);
    }

    public function test_user_creates_a_plan_with_circles_people_and_links(): void
    {
        $user = User::factory()->create();
        $circle = Circle::factory()->for($user)->create(['name' => 'Pareja de prueba']);
        $person = Relationship::factory()->for($user)->create();
        $foreignPerson = Relationship::factory()->create();

        $this->actingAs($user);

        Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Heladería Popsy')
            ->set('planType', 'dessert')
            ->set('planCity', 'Popayán')
            ->set('planCircles', [$circle->id])
            ->set('planPeople', [$person->id, $foreignPerson->id])
            ->set('planLinks', [['url' => 'https://www.instagram.com/popsy', 'label' => ''], ['url' => '', 'label' => '']])
            ->call('savePlan')
            ->assertHasNoErrors()
            ->assertSet('showPlanForm', false);

        $plan = Plan::query()->with(['circles', 'relationships', 'links'])->sole();

        $this->assertSame('pending', $plan->status);
        $this->assertSame([$circle->id], $plan->circles->pluck('id')->all());
        $this->assertSame([$person->id], $plan->relationships->pluck('id')->all());
        $this->assertCount(1, $plan->links);
        $this->assertSame('Instagram', $plan->links->first()->platform()['label']);
    }

    public function test_image_urls_keep_their_order_and_the_first_is_main(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Termales de Coconuco')
            ->set('planImages', ['https://example.com/piscina.jpg', '', 'https://example.com/montana.jpg'])
            ->call('movePlanImageUp', 2)
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::query()->with('images')->sole();
        $this->assertSame(['https://example.com/piscina.jpg', 'https://example.com/montana.jpg'], $plan->images->pluck('url')->all());
        $this->assertSame('https://example.com/piscina.jpg', $plan->mainImageUrl());

        $component->call('openPlanForm', $plan->id)
            ->assertSet('planImages', ['https://example.com/piscina.jpg', 'https://example.com/montana.jpg'])
            ->call('movePlanImageUp', 1)
            ->call('savePlan');

        $this->assertSame('https://example.com/montana.jpg', $plan->fresh()->mainImageUrl());
        $this->get('/plans')->assertSee('https://example.com/montana.jpg', false);
    }

    public function test_invalid_image_urls_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Picnic')
            ->set('planImages', ['no-es-una-url'])
            ->call('savePlan')
            ->assertHasErrors(['planImages.0']);

        $this->assertSame(0, Plan::query()->count());
    }

    public function test_overdue_badge_respects_the_inclusive_end_date(): void
    {
        $this->actingAs(User::factory()->create());
        $plan = Plan::factory()->create([
            'title' => 'Pizza Master', 'status' => 'scheduled',
            'scheduled_on' => '2026-10-19', 'ends_on' => '2026-10-25',
        ]);

        foreach (['2026-10-18 12:00:00', '2026-10-22 12:00:00', '2026-10-25 23:59:59', '2026-10-26 00:00:00'] as $instant) {
            $this->travelTo(\Illuminate\Support\Carbon::parse($instant));
            $view = Livewire::test(PlanIndex::class);
            if ($instant >= '2026-10-26') {
                $view->assertSee('Vencido');
            } else {
                $view->assertDontSee('Vencido');
            }
        }

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-24 23:59:59'));
        foreach (['2026-10-23' => true, '2026-10-24' => false, '2026-10-25' => false, '' => false] as $date => $overdue) {
            $plan->update(['ends_on' => null, 'scheduled_on' => $date ?: null]);
            $view = Livewire::test(PlanIndex::class);
            $overdue ? $view->assertSee('Vencido') : $view->assertDontSee('Vencido');
        }
        foreach (['pending', 'done', 'archived'] as $status) {
            $plan->update(['status' => $status, 'scheduled_on' => '2026-10-19']);
            Livewire::test(PlanIndex::class)->set('status', $status)->assertSee('Pizza Master')->assertDontSee('Vencido');
        }
        $this->travelBack();
    }

    public function test_a_dated_plan_starts_as_scheduled(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PlanIndex::class)
            ->call('openPlanForm')
            ->set('planTitle', 'Concierto Morat')
            ->set('planType', 'concert')
            ->set('planScheduledOn', today()->addDays(64)->toDateString())
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::query()->sole();
        $this->assertSame('scheduled', $plan->status);
        $this->assertSame(64, $plan->daysUntil());
    }

    public function test_recording_a_visit_with_several_people_updates_counts(): void
    {
        $user = User::factory()->create();
        $ali = Relationship::factory()->for($user)->create();
        $julian = Relationship::factory()->for($user)->create();
        $plan = Plan::factory()->for($user)->create();

        $this->actingAs($user);

        Livewire::test(PlanShow::class, ['plan' => $plan->id])
            ->call('openVisitForm', $plan->id)
            ->set('visitDate', today()->subMonths(3)->toDateString())
            ->set('visitPeople', [$ali->id, $julian->id])
            ->set('visitComment', 'Probamos el de maracuyá.')
            ->call('saveVisit')
            ->assertHasNoErrors()
            ->call('openVisitForm', $plan->id)
            ->set('visitDate', today()->subMonth()->toDateString())
            ->set('visitPeople', [$ali->id])
            ->call('saveVisit')
            ->assertHasNoErrors();

        $global = Plan::query()->withVisitStats()->find($plan->id);
        $this->assertSame(2, (int) $global->visits_count);
        $this->assertStringStartsWith(today()->subMonth()->toDateString(), (string) $global->last_visited_on);

        $withJulian = Plan::query()->withVisitStats($julian->id)->find($plan->id);
        $this->assertSame(1, (int) $withJulian->visits_count);
        $this->assertStringStartsWith(today()->subMonths(3)->toDateString(), (string) $withJulian->last_visited_on);

        $this->assertEqualsCanonicalizing([$ali->id, $julian->id], $plan->relationships()->pluck('relationships.id')->all());
    }

    public function test_a_visit_needs_at_least_one_person_and_a_past_date(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->for($user)->create();

        $this->actingAs($user);

        Livewire::test(PlanShow::class, ['plan' => $plan->id])
            ->call('openVisitForm', $plan->id)
            ->set('visitDate', today()->addDay()->toDateString())
            ->set('visitPeople', [])
            ->call('saveVisit')
            ->assertHasErrors(['visitDate', 'visitPeople']);

        $this->assertSame(0, PlanVisit::query()->count());
    }

    public function test_a_scheduled_plan_is_done_after_its_visit(): void
    {
        $user = User::factory()->create();
        $person = Relationship::factory()->for($user)->create();
        $plan = Plan::factory()->for($user)->scheduled(today()->toDateString())->create();

        $this->actingAs($user);

        Livewire::test(PlanShow::class, ['plan' => $plan->id])
            ->call('openVisitForm', $plan->id)
            ->set('visitPeople', [$person->id])
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertSame('done', $plan->fresh()->status);
    }

    public function test_plans_are_isolated_per_user(): void
    {
        $user = User::factory()->create();
        $other = Plan::factory()->create(['title' => 'Plan ajeno secreto']);

        $this->actingAs($user)->get('/plans')->assertOk()->assertDontSee('Plan ajeno secreto');
        $this->actingAs($user)->get("/plans/{$other->id}")->assertNotFound();
    }

    public function test_surprise_only_picks_own_open_plans(): void
    {
        $user = User::factory()->create();
        $pending = Plan::factory()->for($user)->create();
        Plan::factory()->for($user)->create(['status' => 'done']);
        Plan::factory()->create();

        $this->actingAs($user);

        Livewire::test(PlanIndex::class)
            ->call('surprise')
            ->assertRedirect(route('plans.show', $pending));
    }

    public function test_surprise_explains_when_nothing_matches(): void
    {
        $user = User::factory()->create();
        Plan::factory()->for($user)->create(['type' => 'trip']);

        $this->actingAs($user);

        Livewire::test(PlanIndex::class)
            ->call('setGroup', 'events')
            ->call('surprise')
            ->assertNoRedirect()
            ->assertSet('surpriseMessage', 'No hay planes pendientes con estos filtros.');
    }

    public function test_group_and_people_filters_narrow_the_list(): void
    {
        $user = User::factory()->create();
        $person = Relationship::factory()->for($user)->create();
        $trip = Plan::factory()->for($user)->create(['title' => 'Cartagena', 'type' => 'trip']);
        Plan::factory()->for($user)->create(['title' => 'La Cosecha', 'type' => 'restaurant']);
        $trip->relationships()->attach($person->id);

        $this->actingAs($user);

        Livewire::test(PlanIndex::class)
            ->call('setGroup', 'trips')
            ->assertSee('Cartagena')
            ->assertDontSee('La Cosecha')
            ->call('setGroup', '')
            ->set('people', [$person->id])
            ->assertSee('Cartagena')
            ->assertDontSee('La Cosecha');
    }

    public function test_relationship_tab_lists_plans_of_the_person_and_their_circle(): void
    {
        $user = User::factory()->create();
        $circle = Circle::factory()->for($user)->create();
        $person = Relationship::factory()->for($user)->create(['circle_id' => $circle->id]);

        Plan::factory()->for($user)->create(['title' => 'Plan directo'])->relationships()->attach($person->id);
        Plan::factory()->for($user)->create(['title' => 'Plan del círculo'])->circles()->attach($circle->id);
        Plan::factory()->for($user)->create(['title' => 'Plan de otra persona']);

        $this->actingAs($user);

        Livewire::test(RelationshipPlans::class, ['relationship' => $person->id])
            ->assertSee('Plan directo')
            ->assertSee('Plan del círculo')
            ->assertDontSee('Plan de otra persona')
            ->call('openPlanForm')
            ->assertSet('planPeople', [$person->id]);
    }

    public function test_scheduled_defaults_to_today_and_can_include_overdue_plans(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Plan::factory()->for($user)->scheduled(today()->subDay()->toDateString())->create(['title' => 'Vencido']);
        Plan::factory()->for($user)->scheduled(today()->toDateString())->create(['title' => 'Hoy']);
        Plan::factory()->for($user)->scheduled(today()->addDay()->toDateString())->create(['title' => 'Mañana']);
        Plan::factory()->for(User::factory()->create())->scheduled(today()->toDateString())->create(['title' => 'Ajeno']);
        Livewire::test(PlanScheduled::class)
            ->assertSet('from', today()->toDateString())->assertSet('sort', 'upcoming')
            ->assertSee('Hoy')->assertSee('Mañana')->assertDontSee('Vencido')->assertDontSee('Ajeno')
            ->set('from', today()->subDays(2)->toDateString())->assertSee('Vencido')
            ->set('to', today()->toDateString())->assertDontSee('Mañana')
            ->set('q', 'inexistente')->assertSee('Prueba con otro grupo');
    }

    public function test_history_search_dates_people_and_deletion_preserve_plan_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = Plan::factory()->for($user)->create(['title' => 'Paseo', 'status' => 'done']);
        $person = Relationship::factory()->for($user)->create(['full_name' => 'Contacto Historia']);
        $visit = $plan->visits()->create(['visited_on' => today()->subDay(), 'comment' => 'Comentario único']);
        $visit->relationships()->attach($person);
        $otherPlan = Plan::factory()->for(User::factory()->create())->create();
        $foreign = PlanVisit::forceCreate(['user_id' => $otherPlan->user_id, 'plan_id' => $otherPlan->id, 'visited_on' => today(), 'comment' => 'Privado']);
        Livewire::test(PlanHistory::class)
            ->assertSee('Paseo')->assertDontSee('Privado')
            ->set('q', 'único')->assertSee('Paseo')->set('q', 'Contacto Historia')->assertSee('Paseo')
            ->set('q', '')->set('people', [$person->id])->assertSee('Paseo')
            ->set('from', today()->toDateString())->assertDontSee('Paseo')->set('from', '')
            ->call('showVisit', $visit->id)->assertSet('viewingId', $visit->id)
            ->call('deleteVisit', $visit->id)->assertSet('viewingId', null)->assertSee('No hay visitas');
        $this->assertDatabaseMissing('plan_visits', ['id' => $visit->id]);
        $this->assertDatabaseMissing('plan_visit_relationship', ['plan_visit_id' => $visit->id]);
        $this->assertSame('done', $plan->fresh()->status);
        $this->assertSame(0, Plan::query()->withVisitStats()->find($plan->id)->visits_count);
        foreach (['deleteVisit', 'showVisit'] as $method) {
            try {
                Livewire::test(PlanHistory::class)->call($method, $foreign->id);
                $this->fail('Foreign visit accessible');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_categories_resolve_legacy_text_rename_and_block_in_use_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = SavePlan::handle(new Plan, ['type' => 'restaurant', 'title' => 'Cena', 'category' => '  Comida   rica ']);
        $second = SavePlan::handle(new Plan, ['type' => 'restaurant', 'title' => 'Almuerzo', 'category' => 'comida rica']);
        $this->assertSame($plan->plan_category_id, $second->plan_category_id);
        $category = PlanCategory::query()->sole();
        Livewire::test(PlanSettings::class)->set('section', 'categories')->call('openForm', $category->id)
            ->set('name', 'Gastronomía')->set('icon', 'bi-tree')->call('save')->assertHasNoErrors();
        $this->assertSame('Gastronomía', $plan->fresh()->category);
        Livewire::test(PlanSettings::class)->set('section', 'categories')->call('delete', $category->id)->assertSee('está asociada a 2 planes');
        Livewire::test(PlanIndex::class)->set('category', $category->id)->assertSee('Cena')->assertSee('Almuerzo');
        SavePlan::handle($plan, ['category' => null]);
        $this->assertNull($plan->fresh()->plan_category_id);
    }

    public function test_category_backfill_keeps_owners_and_consolidates_names(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $a = Plan::factory()->for($first)->create(['category' => '  Aire   libre ']);
        $b = Plan::factory()->for($first)->create(['category' => 'aire libre']);
        $c = Plan::factory()->for($second)->create(['category' => 'Aire libre']);
        $empty = Plan::factory()->for($first)->create(['category' => '   ']);
        $migration = require database_path('migrations/2026_10_12_000002_create_plan_categories_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame($a->fresh()->plan_category_id, $b->fresh()->plan_category_id);
        $this->assertNotSame($a->fresh()->plan_category_id, $c->fresh()->plan_category_id);
        $this->assertNull($empty->fresh()->plan_category_id);
        $this->assertSame('Aire libre', $a->fresh()->category);
    }

    public function test_foreign_categories_tags_and_contacts_are_protected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = PlanCategory::forceCreate(['user_id' => $other->id, 'name' => 'Privada', 'icon' => 'bi-grid']);
        $tag = PlanTag::forceCreate(['user_id' => $other->id, 'name' => 'Privado', 'icon' => 'bi-tag']);
        $this->actingAs($user);
        foreach ([['categories', 'openForm', $foreign->id], ['categories', 'delete', $foreign->id], ['tags', 'delete', $tag->id]] as [$section,$method,$id]) {
            try {
                Livewire::test(PlanSettings::class)->set('section', $section)->call($method, $id);
                $this->fail('Foreign catalog accessible');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        Livewire::test(PlanIndex::class)->call('openPlanForm')->set('planTitle', 'Prueba')->set('planCategoryId', $foreign->id)->call('savePlan')->assertHasErrors('planCategoryId');
    }

    public function test_contact_chips_link_to_real_relationship_and_icons_validate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $person = Relationship::factory()->for($user)->create();
        $plan = Plan::factory()->for($user)->create();
        $plan->relationships()->attach($person);
        $this->get('/plans')->assertSee(route('relationships.show', $person), false)->assertSee('wire:navigate', false);
        Livewire::test(PlanSettings::class)->call('openForm')->set('name', 'Tag válido')->set('icon', 'bi-calendar-week')->call('save')->assertHasNoErrors();
        Livewire::test(PlanSettings::class)->call('openForm')->set('name', 'Tag malo')->set('icon', 'bi-inventado')->call('save')->assertHasErrors('icon');
    }

    public function test_history_sort_pagination_and_invalid_dates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = Plan::factory()->for($user)->create();
        foreach (range(1, 12) as $day) {
            $plan->visits()->create(['visited_on' => today()->subDays($day), 'comment' => 'Visita '.$day]);
        }
        Livewire::test(PlanHistory::class)->set('perPage', 10)
            ->assertViewHas('visits', fn ($rows) => $rows->count() === 10 && $rows->first()->comment === 'Visita 1')
            ->call('gotoPage', 2)->assertViewHas('visits', fn ($rows) => $rows->count() === 2)
            ->set('sort', 'oldest')->assertViewHas('visits', fn ($rows) => $rows->first()->comment === 'Visita 12')
            ->set('from', 'invalid')->assertHasErrors('from');
    }

    public function test_database_rejects_cross_user_category_assignment(): void
    {
        $user = User::factory()->create();
        $category = PlanCategory::forceCreate(['user_id' => User::factory()->create()->id, 'name' => 'Privada', 'icon' => 'bi-grid']);
        $plan = Plan::factory()->for($user)->create();
        $this->expectException(QueryException::class);
        DB::table('plans')->where('id', $plan->id)->update(['plan_category_id' => $category->id]);
    }

    public function test_category_resolution_uses_explicit_owner_without_authentication(): void
    {
        $user = User::factory()->create();
        auth()->logout();
        $plan = new Plan;
        $plan->user_id = $user->id;
        SavePlan::handle($plan, ['title' => 'Plan sin sesión', 'type' => 'place', 'category' => 'Naturaleza']);
        $this->assertSame($user->id, PlanCategory::withoutGlobalScopes()->findOrFail($plan->plan_category_id)->user_id);
    }
}
