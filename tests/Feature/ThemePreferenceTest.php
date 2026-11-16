<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_follow_the_device_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame('system', $user->fresh()->themePreference());
    }

    public function test_user_can_save_each_valid_preference(): void
    {
        $user = User::factory()->create();

        foreach (['dark', 'light', 'system'] as $theme) {
            $this->actingAs($user)->patchJson(route('appearance.update'), ['theme' => $theme])
                ->assertOk()->assertJson(['theme' => $theme]);

            $this->assertSame($theme, $user->fresh()->theme_preference);
        }
    }

    public function test_invalid_preference_is_rejected_and_not_saved(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);

        $this->actingAs($user)->patchJson(route('appearance.update'), ['theme' => 'neon'])
            ->assertUnprocessable();
        $this->actingAs($user)->patchJson(route('appearance.update'), [])->assertUnprocessable();

        $this->assertSame('dark', $user->fresh()->theme_preference);
    }

    public function test_guests_cannot_change_the_preference(): void
    {
        $this->patchJson(route('appearance.update'), ['theme' => 'dark'])->assertUnauthorized();
    }

    public function test_preference_only_changes_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patchJson(route('appearance.update'), ['theme' => 'dark'])->assertOk();

        $this->assertSame('system', $other->fresh()->theme_preference);
    }

    public function test_unknown_stored_value_falls_back_to_system(): void
    {
        $user = User::factory()->make(['theme_preference' => 'sepia']);

        $this->assertSame('system', $user->themePreference());
    }

    public function test_settings_profile_lists_profile_before_appearance_and_menu_shows_picker(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);

        $html = $this->actingAs($user)->get(route('settings'))->assertOk()->getContent();

        $this->assertStringContainsString("var pref = \"dark\"", str_replace("'", '"', $html));
        $this->assertLessThan(strpos($html, 'Elige cómo quieres ver MiSo'), strpos($html, 'Perfil público'));
        $this->assertStringContainsString('role="radiogroup"', $html);
    }
}
