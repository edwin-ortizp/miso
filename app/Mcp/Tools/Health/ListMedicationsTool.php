<?php

namespace App\Mcp\Tools\Health;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Support\MedicationSchedule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Medicamentos del usuario. scope=today (por defecto): las tomas de hoy con su estado (pending, overdue, taken, skipped) y cuántas faltan; úsala para «¿qué me falta tomar hoy?». scope=active: los medicamentos programados y su frecuencia. scope=history: las tomas registradas en un periodo (por defecto 14 días), con cuántas se tomaron y se omitieron. MiSo registra; no recomienda dosis ni detecta interacciones. Los medicamentos y tomas marcados como sensibles se omiten salvo include_sensitive=true; hidden_sensitive dice cuántos se ocultaron.')]
class ListMedicationsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'scope' => ['nullable', 'in:today,active,history'],
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'include_sensitive' => ['nullable', 'boolean'],
        ]);

        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);

        return match ($data['scope'] ?? 'today') {
            'active' => $this->active($includeSensitive),
            'history' => $this->history($data, $includeSensitive),
            default => $this->today($includeSensitive),
        };
    }

    private function today(bool $includeSensitive): ResponseFactory
    {
        $hidden = $includeSensitive ? 0 : Medication::where('is_active', true)->where('is_sensitive', true)->count();
        $rows = MedicationSchedule::forDay(now(), null, $includeSensitive);

        return Response::structured(McpOutput::compact([
            'date' => now()->toDateString(),
            'hidden_sensitive' => $hidden ?: null,
            'summary' => [
                'scheduled' => $rows->count(),
                'taken' => $rows->where('state', 'taken')->count(),
                'skipped' => $rows->where('state', 'skipped')->count(),
                'overdue' => $rows->where('state', 'overdue')->count(),
                'pending' => $rows->where('state', 'pending')->count(),
            ],
            'doses' => $rows->map(fn (array $row) => [
                'medication_id' => $row['medication']->id,
                'name' => $row['medication']->name,
                'dose' => $row['medication']->doseText(),
                'scheduled_for' => $row['scheduled_for']->format('Y-m-d H:i'),
                'state' => $row['state'],
                'taken_at' => $row['intake']?->taken_at?->format('H:i'),
                'instructions' => $row['medication']->instructions,
            ])->values()->all(),
        ]));
    }

    private function active(bool $includeSensitive): ResponseFactory
    {
        $query = Auth::user()->medications()->where('is_active', true);
        $hidden = $includeSensitive ? 0 : (clone $query)->where('is_sensitive', true)->count();
        $medications = $query->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))->orderBy('name')->get();

        return Response::structured(McpOutput::compact([
            'hidden_sensitive' => $hidden ?: null,
            'medications' => $medications->map(fn (Medication $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'dose' => $m->doseText(),
                'form' => $m->form,
                'frequency' => $m->frequencyText(),
                'starts_on' => $m->starts_on->toDateString(),
                'ends_on' => $m->ends_on?->toDateString(),
                'instructions' => $m->instructions,
                'notes' => $m->notes,
            ])->values()->all(),
        ]));
    }

    /** @param  array<string, mixed>  $data */
    private function history(array $data, bool $includeSensitive): ResponseFactory
    {
        $window = DateWindow::fromInput($data, $data['days'] ?? 14);

        // Las puntuales no tienen scheduled_for: se buscan por taken_at.
        $query = Auth::user()->medicationIntakes()->where(function ($q) use ($window) {
            $q->where(fn ($q) => $q->whereNotNull('scheduled_for')
                ->whereDate('scheduled_for', '>=', $window->fromDate())->whereDate('scheduled_for', '<=', $window->toDate()))
                ->orWhere(fn ($q) => $q->whereNull('scheduled_for')
                    ->whereDate('taken_at', '>=', $window->fromDate())->whereDate('taken_at', '<=', $window->toDate()));
        });

        $hidden = $includeSensitive ? 0 : (clone $query)->where('is_sensitive', true)->count();
        $items = $query->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))->get()
            ->sortByDesc(fn (MedicationIntake $i) => $i->occurredAt()->timestamp)->values();

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'hidden_sensitive' => $hidden ?: null,
            'summary' => [
                'taken' => $items->where('status', 'taken')->count(),
                'skipped' => $items->where('status', 'skipped')->count(),
                'by_medication' => $items->groupBy('name')->map(fn ($group, $name) => [
                    'name' => $name,
                    'taken' => $group->where('status', 'taken')->count(),
                    'skipped' => $group->where('status', 'skipped')->count(),
                ])->values()->all(),
            ],
            'intakes' => $items->take(80)->map(fn (MedicationIntake $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'dose' => $i->doseText(),
                'status' => $i->status,
                'scheduled_for' => $i->scheduled_for?->format('Y-m-d H:i'),
                'taken_at' => $i->taken_at?->format('Y-m-d H:i'),
                'skip_reason' => $i->skip_reason,
                'unscheduled' => $i->scheduled_for === null,
            ])->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()->description('today (por defecto), active o history.'),
            'since' => $schema->string()->description('Solo history: desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Solo history: hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Solo history: días hacia atrás si no envías since (por defecto 14).'),
            'include_sensitive' => $schema->boolean()->description('Incluye lo marcado como sensible. Úsalo solo si el tema actual lo necesita.'),
        ];
    }
}
