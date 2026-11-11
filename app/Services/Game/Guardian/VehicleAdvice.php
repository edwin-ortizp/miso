<?php

namespace App\Services\Game\Guardian;

use App\Models\VehicleEnergyLog;
use App\Models\VehicleMaintenancePlan;
use App\Support\VehicleMaintenanceStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Consejos de Vehículos (CHG-064): mantenimiento vencido o que vence en ≤ 14 días
 * (reusa VehicleMaintenanceStatus) y días sin registrar carga. 3 consultas.
 */
class VehicleAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = Carbon::parse($now)->startOfDay();

        $plans = VehicleMaintenancePlan::query()->where('active', true)
            ->with(['vehicle:id,name,current_usage', 'template:id,name', 'latestMaintenanceLog'])->get();
        $due = $plans->map(fn ($p) => [$p, VehicleMaintenanceStatus::forPlan($p, $today, false)['due_date']])
            ->filter(fn ($x) => $x[1] && $x[1]->lte($today->copy()->addDays(14)) && $x[0]->vehicle)
            ->sortBy(fn ($x) => $x[1]->timestamp)->first();
        if ($due) {
            [$plan, $date] = $due;
            $days = (int) $today->diffInDays($date->copy()->startOfDay(), false);
            $when = match (true) { $days < 0 => 'está pendiente desde hace '.abs($days).' días', $days === 0 => 'vence hoy', $days === 1 => 'vence mañana', default => "vence en {$days} días" };
            $out[] = $this->candidate('vehicles', 'maintenance', [
                'task' => $plan->template?->name ?? 'El mantenimiento', 'vehicle' => $plan->vehicle->name, 'when' => $when,
            ], 3, ['task', 'vehicle']);
        }

        $vehicles = $plans->pluck('vehicle')->filter()->unique('id');
        if ($vehicles->isNotEmpty()) {
            $last = VehicleEnergyLog::query()->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->selectRaw('vehicle_id, MAX(recorded_on) as last_on')->groupBy('vehicle_id')->get()
                ->map(fn ($l) => [$l->vehicle_id, (int) Carbon::parse($l->last_on)->startOfDay()->diffInDays($today)])
                ->filter(fn ($x) => $x[1] >= 21)->sortByDesc(fn ($x) => $x[1])->first();
            if ($last) {
                $out[] = $this->candidate('vehicles', 'fuel', ['days' => $last[1], 'vehicle' => $vehicles->firstWhere('id', $last[0])->name], 2, ['vehicle']);
            }
        }

        return $out;
    }
}
