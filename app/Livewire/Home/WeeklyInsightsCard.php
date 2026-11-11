<?php

namespace App\Livewire\Home;

use App\Models\WeeklyInsight;
use App\Services\Insights\WeeklyInsights;
use Livewire\Component;

/** CHG-071: "Lo que dicen tus datos" en Inicio, domingo y lunes; se puede cerrar. */
class WeeklyInsightsCard extends Component
{
    public ?int $insightId = null;

    public function mount(WeeklyInsights $insights): void
    {
        $now = now();
        if (! in_array($now->dayOfWeekIso, [7, 1], true)) {
            return;
        }
        // El lunes muestra la semana que acaba de terminar.
        $insight = $insights->forWeek(auth()->user(), $now->dayOfWeekIso === 1 ? $now->copy()->subDay() : $now);
        $this->insightId = $insight->dismissed_at ? null : $insight->id;
    }

    public function dismiss(): void
    {
        WeeklyInsight::where('user_id', auth()->id())->whereKey($this->insightId)->update(['dismissed_at' => now()]);
        $this->insightId = null;
    }

    public function render()
    {
        return view('livewire.home.weekly-insights-card', [
            'insight' => $this->insightId ? WeeklyInsight::where('user_id', auth()->id())->find($this->insightId) : null,
        ]);
    }
}
