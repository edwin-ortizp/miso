<?php

namespace Tests\Feature;

use App\Livewire\Plan\PlanIndex;
use App\Livewire\Plan\PlanShow;
use App\Livewire\Task\TaskCalendar;
use App\Models\City;
use App\Models\Country;
use App\Models\Plan;
use App\Models\User;
use App\Services\Experience\PersonalExperience;
use App\Services\Geography\LocationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class PlanDetailsAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_city_requires_a_choice_and_address_links_use_available_context(): void
    {
        $this->actingAs($user = User::factory()->create());
        Country::create(['code' => 'CO', 'name' => 'Colombia']);
        Country::create(['code' => 'MX', 'name' => 'México']);
        City::create(['id' => 1, 'country_code' => 'CO', 'name' => 'Popayán', 'normalized_name' => 'popayan', 'region' => 'Cauca']);
        City::create(['id' => 2, 'country_code' => 'MX', 'name' => 'Puebla', 'normalized_name' => 'puebla']);
        $plan = Plan::factory()->for($user)->create(['city' => 'Popayan anterior', 'address' => 'Calle 1 #2 & 3']);

        Livewire::test(PlanShow::class, ['plan' => $plan->id])
            ->assertSee('Popayan anterior')
            ->call('openPlanForm', $plan->id, 'links')
            ->assertSet('planFormSection', 'links')
            ->set('planLinks', [['url' => 'https://example.com', 'label' => 'Página']])
            ->call('savePlan')
            ->assertHasErrors('planCityId')
            ->assertSet('planInvalidSection', 'basic')
            ->set('planCountryCode', 'CO')
            ->set('planCityId', '2')
            ->call('savePlan')
            ->assertHasErrors('planCityId')
            ->set('planCityId', '1')
            ->set('planCountryCode', 'MX')
            ->assertSet('planCityId', '')
            ->set('planCountryCode', 'CO')
            ->set('planCityId', '1')
            ->call('savePlan')
            ->assertHasNoErrors()
            ->assertSee('Popayán · Cauca')
            ->assertSee('Calle%201%20%232%20%26%203%2C%20Popay%C3%A1n%2C%20Cauca%2C%20Colombia', false);

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'city_id' => 1, 'country_code' => 'CO', 'city' => 'Popayán']);
        $this->assertSame('Página', $plan->links()->sole()->label);
        Livewire::test(PlanIndex::class)->set('city', 'Popayán')->assertSee($plan->title);

        $legacy = Plan::factory()->for($user)->create(['city' => 'Lugar anterior', 'address' => 'Calle 2']);
        Livewire::test(PlanShow::class, ['plan' => $legacy->id])
            ->call('openPlanForm', $legacy->id, 'people')
            ->call('removePreviousPlanCity')
            ->call('savePlan')
            ->assertHasNoErrors()
            ->assertSee('Calle%202', false);

        $this->assertNull($legacy->fresh()->city);
    }

    public function test_location_resolution_accepts_empty_values_and_rejects_ambiguous_names(): void
    {
        Country::create(['code' => 'CO', 'name' => 'Colombia']);
        Country::create(['code' => 'MX', 'name' => 'México']);
        foreach (['CO', 'MX'] as $index => $country) {
            City::create(['id' => $index + 1, 'country_code' => $country, 'name' => 'La Unión', 'normalized_name' => 'la union']);
        }

        $this->assertSame(['country_code' => null, 'city_id' => null, 'city' => null], LocationCatalog::attributes([]));
        $this->assertSame(['country_code' => 'CO', 'city_id' => null, 'city' => null], LocationCatalog::attributes(['country_code' => 'CO']));
        $this->assertSame(1, LocationCatalog::attributes(['country_code' => 'CO', 'city' => 'la union'])['city_id']);
        $this->assertSame([2 => 'La Unión'], LocationCatalog::cityOptions('MX', 'la un', ''));

        $this->expectException(ValidationException::class);
        LocationCatalog::attributes(['city' => 'La Unión']);
    }

    public function test_geonames_import_can_be_rerun_and_rolls_back_an_incomplete_snapshot(): void
    {
        $directory = sys_get_temp_dir().'/miso-geonames-'.uniqid();
        mkdir($directory);

        try {
            file_put_contents($directory.'/countryInfo.txt', "CO\tCOL\t170\tCO\tColombia\n");
            file_put_contents($directory.'/admin1CodesASCII.txt', "CO.09\tCauca\n");
            $row = ['3671916', 'Popayán', 'Popayan', '', '2.44', '-76.61', 'P', 'PPLA', 'CO', '', '09', '', '', '', '258653', '', '', 'America/Bogota', '2026-11-18'];
            $writeArchive = function (string $content) use ($directory): void {
                $zip = new ZipArchive;
                $this->assertTrue($zip->open($directory.'/cities500.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE));
                $zip->addFromString('cities500.txt', $content);
                $zip->close();
            };
            $writeArchive(implode("\t", $row)."\n");

            $this->artisan('geography:import', ['directory' => $directory])->assertSuccessful();
            $this->artisan('geography:import', ['directory' => $directory])->assertSuccessful();
            $this->assertDatabaseCount('countries', 1);
            $this->assertDatabaseCount('cities', 1);
            $this->assertSame('Popayán · Cauca', City::findOrFail(3671916)->label());

            file_put_contents($directory.'/countryInfo.txt', "CO\tCOL\t170\tCO\tCambio\n");
            $writeArchive("incomplete\n");
            $this->artisan('geography:import', ['directory' => $directory])->assertFailed();
            $this->assertSame('Colombia', Country::findOrFail('CO')->name);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_optional_times_validate_and_project_the_actual_calendar_interval(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(PlanIndex::class)->call('openPlanForm')
            ->set('planTitle', 'Cine')
            ->set('planEndsAtTime', '21:00')
            ->call('savePlan')->assertHasErrors('planStartsAtTime')
            ->set('planStartsAtTime', '19:00')
            ->call('savePlan')->assertHasErrors('planScheduledOn')
            ->set('planScheduledOn', '2026-11-19')
            ->set('planEndsAtTime', '18:00')
            ->call('savePlan')->assertHasErrors('planEndsAtTime')
            ->set('planEndsAtTime', null)
            ->call('savePlan')->assertHasNoErrors();

        $plan = Plan::query()->sole();
        Livewire::test(PlanShow::class, ['plan' => $plan->id])->assertSee('19 nov. 2026 · 7:00 p. m.');
        $calendar = Livewire::test(TaskCalendar::class)->instance();
        $event = $calendar->events('2026-11-19', '2026-11-20', ['plans'])[0];
        $this->assertSame('2026-11-19T19:00:00', $event['start']);
        $this->assertNull($event['end']);
        $this->assertFalse($event['allDay']);
        $this->assertSame([], $calendar->events('2026-11-19T17:00:00-05:00', '2026-11-19T19:00:00-05:00', ['plans']));

        Livewire::test(PlanShow::class, ['plan' => $plan->id])->call('openPlanForm', $plan->id)
            ->set('planEndsOn', '2026-11-20')->set('planEndsAtTime', '01:30')
            ->call('savePlan')->assertHasNoErrors()->assertSee('20 nov. 2026 · 1:30 a. m.');

        $this->assertSame('2026-11-20T01:30:00', $calendar->events('2026-11-20', '2026-11-21', ['plans'])[0]['end']);
        $this->assertSame([], $calendar->events('2026-11-20T01:30:00-05:00', '2026-11-21', ['plans']));
        $plan->refresh()->update(['ends_at_time' => null]);
        $this->assertSame('2026-11-21T00:00:00', $calendar->events('2026-11-20', '2026-11-21', ['plans'])[0]['end']);
        $plan->update(['starts_at_time' => null]);
        $event = $calendar->events('2026-11-20', '2026-11-21', ['plans'])[0];
        $this->assertTrue($event['allDay']);
        $this->assertSame('2026-11-21', $event['end']);
    }

    public function test_the_shared_calendar_shortcut_respects_preferences_and_is_the_only_active_main_destination(): void
    {
        $this->actingAs($user = User::factory()->create());
        $html = $this->get(route('tasks.calendar'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<a[^>]*href="[^"]*\/tasks\/calendar"[^>]*class="lt-nav-item is-active"[^>]*aria-current="page"/s', $html);
        $this->assertMatchesRegularExpression('/<a[^>]*href="[^"]*\/tasks\/calendar"[^>]*class="lt-bottom__item is-active"[^>]*aria-current="page"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*href="[^"]*\/tasks\/list"[^>]*class="lt-(?:nav-item|bottom__item) is-active"/s', $html);
        $this->assertStringContainsString('md-module-tab is-active', $html);

        $experience = app(PersonalExperience::class);
        $experience->saveModules($user->id, ['statistics']);
        $this->get(route('home'))->assertSee(route('tasks.calendar'))->assertSee(route('statistics'));
        $experience->saveModules($user->id, ['tasks', 'statistics']);
        $experience->saveFeatures($user->id, ['tasks.calendar']);
        $html = $this->get(route('tasks.list'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('tasks.calendar'), $html);
        $this->assertMatchesRegularExpression('/<a[^>]*href="[^"]*\/statistics"[^>]*class="lt-bottom__item /s', $html);
    }
}
