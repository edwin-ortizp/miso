<?php

namespace App\Observers;

use App\Models\HabitCompletion;
use App\Models\Task;
use App\Services\Experience\PersonalExperience;
use Illuminate\Database\Eloquent\Model;

class ExperienceActivityObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof HabitCompletion || $model instanceof Task) {
            if (! $model->completed || (! $model->wasRecentlyCreated && ! $model->wasChanged('completed'))) {
                return;
            }
        }
        if ((int) $model->user_id > 0) {
            app(PersonalExperience::class)->event((int) $model->user_id, 'first_real_action');
        }
    }
}
