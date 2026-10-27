<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\CalDav\RecurrenceRule;
use App\Services\TaskRecurrenceService;
use Carbon\Carbon;
use Tests\TestCase;

class TaskRecurrenceSuggestionTest extends TestCase
{
    public function test_simple_recurrences_use_the_completion_day_instead_of_the_series_anchor(): void
    {
        $service = new TaskRecurrenceService(new RecurrenceRule);
        $cases = [
            [['pattern' => 'daily', 'frequency' => 1], '2026-10-28'],
            [['pattern' => 'custom', 'customDays' => 3], '2026-10-30'],
            [['pattern' => 'weekly', 'frequency' => 1], '2026-11-03'],
            [['pattern' => 'monthly', 'frequency' => 2], '2026-12-27'],
            [['rrule' => 'FREQ=DAILY;INTERVAL=1'], '2026-10-28'],
        ];

        foreach ($cases as [$recurrence, $expected]) {
            $task = new Task([
                'start_date' => '2026-10-26',
                'recurrence' => [...$recurrence, 'anchor' => '2026-10-25', 'occurrences_completed' => 4],
            ]);
            $completedAt = Carbon::parse('2026-10-27 15:30');
            $this->assertSame($expected, $service->suggestedNextDate($task, $completedAt)->toDateString());
            $this->assertSame('2026-10-27 15:30', $completedAt->format('Y-m-d H:i'));
        }

        $task = new Task(['recurrence' => ['rrule' => 'FREQ=MONTHLY;INTERVAL=1']]);
        $this->assertSame('2027-02-28', $service->suggestedNextDate($task, Carbon::parse('2027-01-31'))->toDateString());
    }

    public function test_calendar_rules_keep_their_anchor_and_occurrence_limits(): void
    {
        $service = new TaskRecurrenceService(new RecurrenceRule);
        foreach (['FREQ=WEEKLY;BYDAY=MO', 'FREQ=WEEKLY;COUNT=3', 'FREQ=WEEKLY;UNTIL=20261130'] as $rule) {
            $task = new Task(['start_date' => '2026-10-19', 'recurrence' => ['rrule' => $rule]]);
            $this->assertSame('2026-10-26', $service->suggestedNextDate($task, Carbon::parse('2026-10-27'))->toDateString());
        }
    }
}
