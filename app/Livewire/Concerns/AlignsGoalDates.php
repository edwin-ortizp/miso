<?php

namespace App\Livewire\Concerns;

/**
 * CHG-119: si el nuevo inicio supera la fecha límite, esta se alinea con él (evita el error
 * "after_or_equal"). La fecha límite es una meta fija: mover el inicio no la desplaza.
 */
trait AlignsGoalDates
{
    public function updatedStartDate(mixed $value): void
    {
        if (filled($value) && filled($this->dueDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->dueDate) && $this->dueDate < $value) {
            $this->dueDate = $value;
        }
    }
}
