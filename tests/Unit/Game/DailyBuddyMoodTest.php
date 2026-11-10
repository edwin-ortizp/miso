<?php

namespace Tests\Unit\Game;

use App\Services\Game\DailyBuddy;
use Carbon\Carbon;
use Tests\TestCase;

/** CHG-049: escenario por tipo y reacción del compañero al avance del día. */
class DailyBuddyMoodTest extends TestCase
{
    public function test_stage_family_by_type_and_neutral_fallback(): void
    {
        $this->assertSame('fuego', DailyBuddy::stageFamily('Fuego'));
        $this->assertSame('agua', DailyBuddy::stageFamily('Hielo'));
        $this->assertSame('mistico', DailyBuddy::stageFamily('Psíquico'));
        $this->assertSame('cielo', DailyBuddy::stageFamily('Inventado'));
        $this->assertSame('cielo', DailyBuddy::stageFamily(null));
    }

    public function test_mood_by_progress_hour_and_free_day(): void
    {
        $morning = Carbon::parse('2026-11-10 09:00');
        $afternoon = Carbon::parse('2026-11-10 16:00');

        $this->assertSame('¡Vas volando!', DailyBuddy::mood(7, 10, $morning)['text']);
        $this->assertSame('happy', DailyBuddy::mood(3, 10, $afternoon)['key']);
        $this->assertSame('Hoy empezamos juntos', DailyBuddy::mood(1, 10, $morning)['text']);
        $this->assertSame('Un pasito y ya cuenta', DailyBuddy::mood(1, 10, $afternoon)['text']);
        $this->assertSame('Día libre, disfrútalo', DailyBuddy::mood(0, 0, $afternoon)['text']);
    }
}
