<?php

namespace Tests\Feature;

use App\Models\MenstrualCycle;
use App\Support\CycleProgress;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CycleProgressTest extends TestCase
{
    private function cycle(string $start, ?string $end = null): MenstrualCycle
    {
        return new MenstrualCycle(['started_on' => $start, 'ended_on' => $end]);
    }

    public function test_no_estimate_with_fewer_than_three_starts(): void
    {
        foreach ([[], [$this->cycle('2026-10-18')], [$this->cycle('2026-09-20'), $this->cycle('2026-10-18')]] as $set) {
            $summary = CycleProgress::summary(collect($set));
            $this->assertNull($summary['predicted_start']);
            $this->assertNull($summary['window_from']);
        }
    }

    public function test_three_starts_give_the_average_and_a_window_of_two_days(): void
    {
        $summary = CycleProgress::summary(collect([
            $this->cycle('2026-09-20', '2026-09-24'), $this->cycle('2026-10-18', '2026-10-22'), $this->cycle('2026-11-15'),
        ]));

        $this->assertSame(3, $summary['starts']);
        $this->assertSame(28, $summary['average_cycle']);
        $this->assertSame(5, $summary['average_period']);
        $this->assertSame('2026-12-13', $summary['predicted_start']->toDateString());
        $this->assertSame('2026-12-11', $summary['window_from']->toDateString());
        $this->assertSame('2026-12-15', $summary['window_to']->toDateString());
        $this->assertTrue($summary['open']);
    }

    public function test_implausible_gaps_are_left_out_of_the_average(): void
    {
        // 2026-06-01 → 2026-09-20 son 111 días (probable registro faltante): no cuenta.
        $summary = CycleProgress::summary(collect([
            $this->cycle('2026-06-01'), $this->cycle('2026-09-20'), $this->cycle('2026-10-18'), $this->cycle('2026-11-15'),
        ]));

        $this->assertSame(2, $summary['intervals']);
        $this->assertSame(28, $summary['average_cycle']);
    }

    public function test_cycle_day_counts_from_the_last_start(): void
    {
        $cycles = collect([$this->cycle('2026-10-18'), $this->cycle('2026-11-15')]);

        $this->assertSame(3, CycleProgress::cycleDay($cycles, Carbon::parse('2026-11-17')));
        $this->assertSame(1, CycleProgress::cycleDay($cycles, Carbon::parse('2026-11-15')));
        $this->assertNull(CycleProgress::cycleDay($cycles, Carbon::parse('2026-11-10')));
        $this->assertNull(CycleProgress::cycleDay(collect(), Carbon::parse('2026-11-17')));
    }

    public function test_calendar_marks_period_logged_and_estimated_days(): void
    {
        $cycles = collect([$this->cycle('2026-09-20', '2026-09-24'), $this->cycle('2026-10-18', '2026-10-22'), $this->cycle('2026-11-15')]);
        $summary = CycleProgress::summary($cycles);
        $logs = collect([new \App\Models\CycleDayLog(['date' => '2026-11-16'])]);

        $weeks = CycleProgress::calendar(Carbon::parse('2026-11-01'), $cycles, $logs, $summary, Carbon::parse('2026-11-17'));
        $days = collect($weeks)->flatten(1)->keyBy(fn ($d) => $d['date']->toDateString());

        $this->assertTrue($days['2026-11-15']['period']);
        $this->assertTrue($days['2026-11-17']['period'], 'un periodo abierto cubre hasta hoy');
        $this->assertFalse($days['2026-11-18']['period']);
        $this->assertTrue($days['2026-11-16']['logged']);
        $this->assertTrue($days['2026-11-17']['today']);
        $this->assertFalse($days['2026-11-01']['estimated']);
        $this->assertSame(1, $weeks[0][0]['date']->dayOfWeekIso, 'las semanas empiezan en lunes');
    }
}
