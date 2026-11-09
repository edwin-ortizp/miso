<?php

namespace App\Livewire\Game;

use App\Services\Game\CompanionProgress;
use App\Services\Game\SpeciesImageProvider;
use App\Services\Game\WeekPlanner;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Equipo y misiones de la semana. La semana se arma al consultarla por primera vez. */
#[Layout('layouts.app')]
#[Title('Semana · Colección')]
class GameWeekView extends Component
{
    public function render(WeekPlanner $planner, SpeciesImageProvider $images, CompanionProgress $progress)
    {
        $week = $planner->forWeek((int) auth()->id(), now());
        $members = $week->members()->with('companion.species')->get();
        $missions = $week->missions()->get();

        return view('livewire.game.game-week', [
            'week' => $week,
            'weekEnd' => $week->week_start->copy()->addDays(6),
            'xpPerLevel' => max(1, (int) config('game.xp_per_level')),
            'members' => $members->filter(fn ($member) => $member->companion?->species !== null)->map(fn ($member) => [
                'member' => $member,
                'species' => $member->companion->species,
                'image' => $images->url($member->companion->species),
                'power' => $progress->power($member->companion),
                'levelXp' => $member->companion->xp % max(1, (int) config('game.xp_per_level')),
            ])->values(),
            'missions' => $missions,
            // Tareas ya contadas en misiones concretas (CHG-026), y las que siguen existiendo.
            'doneTasks' => \Illuminate\Support\Facades\DB::table('game_mission_contributions')
                ->whereIn('game_mission_id', $missions->pluck('id'))
                ->where('contribution_key', 'like', 'task:%')
                ->get(['game_mission_id', 'contribution_key'])
                ->groupBy('game_mission_id')
                ->map(fn ($rows) => $rows->map(fn ($row) => substr($row->contribution_key, 5))->all()),
            'existingTasks' => \App\Models\Task::query()
                ->whereIn('id', $missions->where('type', \App\Models\Game\GameMission::TYPE_SPECIFIC)
                    ->flatMap(fn ($m) => collect($m->params['tasks'] ?? [])->pluck('id')))
                ->pluck('completed', 'id'),
        ]);
    }
}
