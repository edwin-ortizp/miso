<?php

namespace Tests\Unit;

use App\Models\Task;
use Carbon\Carbon;
use Tests\TestCase;

/** CHG-136: textos de la tarea para listas (sin tocar la base de datos). */
class TaskListLabelsTest extends TestCase
{
    public function test_description_preview_is_the_first_line_without_markdown_marks(): void
    {
        $task = new Task(['description' => "\n## **Importante**: revisar el [informe](https://example.test/a) y `enviar`\n- otra línea"]);
        $this->assertSame('Importante: revisar el informe y enviar', $task->descriptionPreview());

        $this->assertSame('comprar leche', (new Task(['description' => '- [ ] comprar leche']))->descriptionPreview());
        $this->assertNull((new Task(['description' => "  \n"]))->descriptionPreview());
        $this->assertNull((new Task)->descriptionPreview());
    }

    public function test_schedule_label_hides_times_for_whole_day_tasks(): void
    {
        $day = Carbon::parse('2026-11-06 00:00:00');
        $label = fn (array $attributes): ?string => (new Task($attributes))->scheduleLabel();

        $this->assertNull($label([]));
        $this->assertSame($day->translatedFormat('d M'), $label(['start_date' => $day, 'start_is_date' => true, 'end_date' => $day, 'end_is_date' => true]));
        $this->assertSame($day->translatedFormat('d M').' – '.$day->copy()->addDays(2)->translatedFormat('d M'),
            $label(['start_date' => $day, 'start_is_date' => true, 'end_date' => $day->copy()->addDays(2), 'end_is_date' => true]));
        $this->assertSame($day->translatedFormat('d M').', 09:30 – 10:15',
            $label(['start_date' => $day->copy()->setTime(9, 30), 'start_is_date' => false, 'end_date' => $day->copy()->setTime(10, 15), 'end_is_date' => false]));
        $this->assertSame($day->translatedFormat('d M').', 18:00', $label(['end_date' => $day->copy()->setTime(18, 0), 'end_is_date' => false]));
    }
}
