<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudyReservation extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = ['title', 'formation_id', 'unit_id', 'date', 'starts_at', 'ends_at', 'frequency', 'interval', 'weekdays', 'until', 'task_ids'];

    protected function casts(): array
    {
        return ['weekdays' => 'array', 'task_ids' => 'array', 'interval' => 'integer'];
    }

    public function exceptions()
    {
        return $this->hasMany(StudyReservationException::class, 'reservation_id');
    }
}
