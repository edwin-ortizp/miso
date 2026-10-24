<?php

namespace App\Models\Traits;

use Illuminate\Support\Carbon;

/** Notification tables store instants in UTC without changing legacy domain dates. */
trait UsesUtcDates
{
    public function freshTimestamp()
    {
        return Carbon::now('UTC');
    }

    protected function asDateTime($value)
    {
        if (is_string($value) && ! is_numeric($value)) {
            return Carbon::parse($value, 'UTC');
        }

        return parent::asDateTime($value)->utc();
    }

    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }
}
