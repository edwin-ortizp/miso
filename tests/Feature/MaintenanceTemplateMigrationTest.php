<?php

namespace Tests\Feature;

use App\Models\MaintenanceTemplate;
use App\Models\User;
use App\Support\DefaultMaintenanceTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenanceTemplateMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration()
    {
        return require database_path('migrations/2026_11_16_000002_seed_maintenance_templates_and_normalize_categories.php');
    }

    public function test_global_templates_exist_without_seeders_and_use_normalized_categories(): void
    {
        $templates = MaintenanceTemplate::withoutGlobalScopes()->whereNull('user_id')->get();
        $this->assertCount(count(DefaultMaintenanceTemplates::all()), $templates);
        $this->assertEmpty(array_intersect($templates->pluck('category')->all(), array_keys(DefaultMaintenanceTemplates::LEGACY_KEYS)));
        $this->assertEmpty(array_diff($templates->pluck('category')->unique()->all(), array_keys(DefaultMaintenanceTemplates::CATEGORIES)));
    }

    public function test_accent_duplicates_merge_and_raw_names_become_labels(): void
    {
        $user = User::factory()->create();
        foreach ([['transmision', 'transmision'], ['transmisión', 'transmisión'], ['motor y fluidos', 'motor y fluidos']] as [$key, $name]) {
            DB::table('user_categories')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'domain' => 'maintenance',
                'key' => $key, 'name' => $name, 'normalized_name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->migration()->up();

        $rows = DB::table('user_categories')->where('user_id', $user->id)->where('domain', 'maintenance')->pluck('name', 'key');
        $this->assertSame(['motor y fluidos' => 'Motor y fluidos', 'transmision' => 'Transmisión'], $rows->all());
    }
}
