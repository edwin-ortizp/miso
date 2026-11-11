<?php

namespace Tests\Feature\Engagement;

use App\Models\User;
use App\Models\UserActiveDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-073: atajos de la app instalada. */
class PwaShortcutsTest extends TestCase
{
    use RefreshDatabase;

    private function shortcuts(): array
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        return $manifest['shortcuts'] ?? [];
    }

    public function test_manifest_has_four_valid_shortcuts(): void
    {
        $shortcuts = $this->shortcuts();
        $this->assertCount(4, $shortcuts);

        foreach ($shortcuts as $shortcut) {
            $this->assertLessThanOrEqual(20, mb_strlen($shortcut['name']));
            $this->assertLessThanOrEqual(12, mb_strlen($shortcut['short_name']));
            $this->assertStringEndsWith('?via=shortcut', $shortcut['url']);
            $this->assertFileExists(public_path(ltrim($shortcut['icons'][0]['src'], '/')));
        }
    }

    public function test_every_shortcut_opens_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();

        foreach ($this->shortcuts() as $shortcut) {
            $this->actingAs($user)->get($shortcut['url'])->assertOk();
        }
    }

    public function test_shortcut_is_a_known_channel(): void
    {
        $this->assertSame('shortcut', UserActiveDay::channel('shortcut'));
    }
}
