<?php

namespace App\Observers\Game;

use App\Models\EnergyEntry;
use App\Models\JournalEntry;
use App\Models\MealPreparation;
use App\Models\MoodEntry;
use App\Models\PlanVisit;
use App\Models\PreparedMeal;
use App\Models\Purchase;
use App\Models\RelationshipEvent;
use App\Models\VehicleEnergyLog;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenanceLog;
use App\Services\Game\ConsistencyXp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Constancia (CHG-042): cualquier registro nuevo de estas fuentes, desde la app
 * o el MCP, cuenta para el día en que se registra (no el de la fecha del
 * registro), así cargar días pasados no multiplica la xp. Nunca lee el contenido.
 */
class ConsistencySourceObserver
{
    /** @var array<class-string<Model>, string> */
    public const SOURCES = [
        VehicleEnergyLog::class => 'vehicle',
        VehicleExpense::class => 'vehicle',
        VehicleMaintenanceLog::class => 'vehicle',
        JournalEntry::class => 'wellbeing',
        MoodEntry::class => 'wellbeing',
        EnergyEntry::class => 'wellbeing',
        RelationshipEvent::class => 'people',
        PlanVisit::class => 'people',
        MealPreparation::class => 'home',
        PreparedMeal::class => 'home',
        Purchase::class => 'home',
    ];

    public function created(Model $model): void
    {
        $source = self::SOURCES[$model::class] ?? null;
        $userId = (int) $model->getAttribute('user_id');
        if ($source === null || $userId <= 0) {
            return;
        }

        DB::afterCommit(fn () => app(ConsistencyXp::class)->grant($userId, $source, $model, now()));
    }
}
