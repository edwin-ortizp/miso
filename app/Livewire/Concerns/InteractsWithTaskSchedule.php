<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

trait InteractsWithTaskSchedule
{
    public ?string $startTime = null;

    public ?string $endTime = null;

    private array $scheduleEdits = [];

    private ?Carbon $previousScheduleStart = null;

    public function loadTaskSchedule(Task $task): void
    {
        $this->startDate = $task->start_date?->format('Y-m-d');
        $this->startTime = $task->start_date && ! $task->start_is_date ? $task->start_date->format('H:i') : null;
        $this->endDate = $task->end_date?->format('Y-m-d');
        $this->endTime = $task->end_date && ! $task->end_is_date ? $task->end_date->format('H:i') : null;
        $this->estimatedTime = $task->estimated_time;
    }

    public function applyDuration(int $minutes): void
    {
        if ($minutes < 0) {
            return;
        }

        $start = $this->scheduleDate($this->startDate, $this->startTime) ?? now()->setSecond(0);
        $this->startDate = $start->toDateString();
        $this->startTime = $start->format('H:i');
        $this->estimatedTime = $minutes;
        $this->size = $this->sizeFromMinutes($minutes);
        $this->setEndFromDuration($start, $minutes);
        $this->resetErrorBag('endDate');
    }

    public function updatingInteractsWithTaskSchedule(string $property): void
    {
        if (! in_array($property, ['startDate', 'startTime', 'endDate', 'endTime', 'estimatedTime'], true)) {
            return;
        }

        if ($this->scheduleEdits === []) {
            $this->previousScheduleStart = $this->scheduleDate($this->startDate, $this->startTime);
        }

        $this->scheduleEdits[$property] = true;
    }

    public function updatedInteractsWithTaskSchedule(): void
    {
        // Livewire assigns all submitted fields before calling the updated hooks.
        $edits = $this->scheduleEdits;
        if ($edits === []) {
            return;
        }

        $this->scheduleEdits = [];
        $this->resetErrorBag('endDate');
        $start = $this->scheduleDate($this->startDate, $this->startTime);
        $end = $this->scheduleDate($this->endDate, $this->endTime);

        if (isset($edits['endDate']) || isset($edits['endTime'])) {
            $this->estimateFromInterval($start, $end);

            return;
        }

        if (isset($edits['estimatedTime'])) {
            if ($start && filled($this->startTime)) {
                $minutes = $this->estimatedTime;

                if ($minutes === null && filled($this->endTime)) {
                    $this->estimateFromInterval($start, $end);
                } elseif ($minutes !== null && $minutes >= 0) {
                    $this->setEndFromDuration($start, $minutes);
                }
            }

            return;
        }

        if ($start) {
            $this->moveEndWithStart($start, $end, isset($edits['startDate']));
        }
    }

    private function moveEndWithStart(Carbon $start, ?Carbon $end, bool $dateChanged): void
    {
        $previousStart = $this->previousScheduleStart;

        if (filled($this->startTime)) {
            $minutes = $this->estimatedTime;

            if ($minutes === null && $previousStart && $end && $end->greaterThanOrEqualTo($previousStart)) {
                $minutes = (int) $previousStart->diffInMinutes($end);
            }

            if ($minutes !== null && $minutes >= 0) {
                $this->estimatedTime = $minutes;
                $this->setEndFromDuration($start, $minutes);

                return;
            }
        }

        if ($end && $dateChanged) {
            // A date-only start shifts the range by calendar days.
            $days = $previousStart
                ? (int) $previousStart->copy()->startOfDay()->diffInDays($start->copy()->startOfDay())
                : 0;
            $this->endDate = max($start->toDateString(), $end->copy()->addDays($days)->toDateString());
            $end = $this->scheduleDate($this->endDate, $this->endTime);
        }

        if ($this->estimatedTime === null) {
            $this->estimateFromInterval($start, $end);
        }
    }

    private function estimateFromInterval(?Carbon $start, ?Carbon $end): void
    {
        if ($start && $end && $end->greaterThanOrEqualTo($start)) {
            $this->estimatedTime = (int) $start->diffInMinutes($end);
            $this->size = $this->sizeFromMinutes($this->estimatedTime);
        }
    }

    private function setEndFromDuration(Carbon $start, int $minutes): void
    {
        $end = $start->copy()->addMinutes($minutes);
        $this->endDate = $end->toDateString();
        $this->endTime = $end->format('H:i');
    }

    protected function taskScheduleData(bool $deriveEstimate = true): ?array
    {
        $start = $this->scheduleDate($this->startDate, $this->startTime);
        $end = $this->scheduleDate($this->endDate, $this->endTime);
        $this->resetErrorBag('endDate');

        if ($start && $end && $end->lessThan($start)) {
            $this->addError('endDate', 'El fin debe ser igual o posterior al inicio.');

            return null;
        }

        return [
            'start_date' => $start,
            'start_is_date' => ! $start || ! filled($this->startTime),
            'end_date' => $end,
            'end_is_date' => ! $end || ! filled($this->endTime),
            'estimated_time' => $deriveEstimate && $start && $end
                ? ((int) $start->diffInMinutes($end) ?: null)
                : $this->estimatedTime,
        ];
    }

    private function scheduleDate(?string $date, ?string $time = null): ?Carbon
    {
        if (! filled($date)) {
            return null;
        }

        $value = $date.' '.($time ?: '00:00');

        try {
            $parsed = Carbon::createFromFormat('!Y-m-d H:i', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $parsed && $parsed->format('Y-m-d H:i') === $value ? $parsed : null;
    }

    private function sizeFromMinutes(int $minutes): string
    {
        return match (true) {
            $minutes < 30 => 'XS',
            $minutes < 60 => 'S',
            $minutes < 120 => 'M',
            $minutes < 240 => 'L',
            default => 'XL',
        };
    }
}
