<?php

namespace Tests\Unit;

use App\Support\CompanionVoice;
use Tests\TestCase;

class CompanionVoiceTest extends TestCase
{
    public function test_catalog_has_sixty_unique_ids_and_the_selected_references(): void
    {
        $catalog = [];
        foreach (str_split('ABCDEF') as $group) {
            $this->assertCount(10, config("companion_voice.{$group}"));
            $catalog = array_merge($catalog, config("companion_voice.{$group}"));
        }
        $this->assertCount(60, $catalog);
        $this->assertCount(60, array_unique($catalog));
        $this->assertSame('¡Hecho! *gritito de celebración*', $catalog['B01']);
        $this->assertSame('¡COMPLETASTE {goal}! *gritito nada discreto*', $catalog['F01']);
    }

    public function test_session_excludes_last_three_and_separates_users_and_groups(): void
    {
        session()->start();
        $history = [];
        foreach (range(1, 20) as $i) {
            $picked = CompanionVoice::pick(7, 'A');
            $this->assertNotContains($picked['id'], array_slice($history, -3));
            $history[] = $picked['id'];
        }
        $this->assertCount(3, session('companion_voice.7.A'));
        CompanionVoice::pick(8, 'A');
        CompanionVoice::pick(7, 'B');
        $this->assertCount(1, session('companion_voice.8.A'));
        $this->assertCount(1, session('companion_voice.7.B'));
    }

    public function test_short_pool_exclusion_fallback_and_literal_substitution(): void
    {
        session()->start();
        $pool = ['one' => '{habit}', 'two' => 'Otro'];
        $literal = '<script>á "{goal}"</script>';
        $this->assertSame($literal, CompanionVoice::fromPool(1, 'test', $pool, ['habit' => $literal], 'two')['text']);
        $this->assertSame('two', CompanionVoice::fromPool(1, 'test', $pool, [], 'one')['id']);
        $this->assertSame('one', CompanionVoice::fromPool(1, 'test', ['one' => 'Solo'], [], 'one')['id']);
        $this->assertSame(['id' => 'fallback', 'text' => 'Registro actualizado.'], CompanionVoice::pick(1, 'missing'));
    }

    public function test_without_started_session_exclusion_still_works(): void
    {
        session()->flush();
        session()->save();
        $this->assertFalse(session()->isStarted());
        $this->assertSame('two', CompanionVoice::fromPool(1, 'test', ['one' => 'Uno', 'two' => 'Dos'], [], 'one')['id']);
        $this->assertNull(session('companion_voice.1.test'));
    }
}
