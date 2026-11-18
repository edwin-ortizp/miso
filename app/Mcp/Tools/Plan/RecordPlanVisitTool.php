<?php

namespace App\Mcp\Tools\Plan;

use App\Actions\RecordPlanVisit;
use App\Actions\SavePlan;
use App\Mcp\Tools\Plan\Concerns\ResolvesPlanTargets;
use App\Models\Plan;
use App\Models\Relationship;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra que el usuario hizo un plan o fue a un lugar con una o más personas, por ejemplo "fui con Ali a Heladería Popsy el sábado". Busca el plan por nombre y a las personas por nombre, apodo o alias, y guarda la fecha y un comentario opcional. Si el lugar no existe puede crearlo con create_if_missing.')]
class RecordPlanVisitTool extends Tool
{
    use ResolvesPlanTargets;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'string'],
            'plan_title' => ['nullable', 'string', 'max:160', 'required_without:plan_id'],
            'contact_names' => ['required', 'array', 'min:1'],
            'contact_names.*' => ['string'],
            'visited_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'create_if_missing' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(Plan::TYPES))],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city_id' => ['nullable', 'integer'],
            'circle_names' => ['nullable', 'array'],
            'circle_names.*' => ['string'],
        ], [
            'visited_on.before_or_equal' => 'La fecha de la visita no puede ser futura.',
            'contact_names.required' => 'Indica con quién fuiste.',
        ]);

        $people = $this->resolveContactIds($data['contact_names']);
        if ($people instanceof Response) {
            return $people;
        }

        $user = Auth::user();
        $title = isset($data['plan_title']) ? trim($data['plan_title']) : null;
        $missing = empty($data['plan_id']) && ! $user->plans()->where('title', 'like', "%{$title}%")->exists();
        $created = false;

        if ($missing && ! empty($data['create_if_missing'])) {
            $circleIds = $this->resolveCircleIds($data['circle_names'] ?? []);
            if ($circleIds instanceof Response) {
                return $circleIds;
            }

            $plan = new Plan;
            $plan->user_id = $user->id;
            SavePlan::handle($plan, [
                'title' => $title,
                'type' => $data['type'] ?? 'place',
                ...array_intersect_key($data, array_flip(['city', 'country_code', 'city_id'])),
            ], $circleIds, $people);
            $created = true;
        } elseif ($missing) {
            return Response::error("No encontré ningún plan que coincida con \"{$title}\". Revisa list-plans-tool o vuelve a llamar con create_if_missing=true para crearlo y registrar la visita.");
        } else {
            $plan = $this->resolvePlan($data['plan_id'] ?? null, empty($data['plan_id']) ? $title : null);
            if ($plan instanceof Response) {
                return $plan;
            }
        }

        $visitedOn = $data['visited_on'] ?? today()->toDateString();

        $duplicate = $plan->visits()
            ->whereDate('visited_on', $visitedOn)
            ->with('relationships')
            ->get()
            ->first(fn ($visit) => $visit->relationships->pluck('id')->sort()->values()->all() === $people->sort()->values()->all());

        if ($duplicate) {
            return Response::error("Ya estaba registrada la visita a \"{$plan->title}\" el {$visitedOn} con esas personas (id: {$duplicate->id}).");
        }

        $previous = $plan->visits()->max('visited_on');
        $visit = RecordPlanVisit::handle($plan, $visitedOn, $people, $data['comment'] ?? null);

        return Response::text($this->summary($plan->refresh(), $visit->load('relationships'), $previous, $created));
    }

    private function summary(Plan $plan, $visit, ?string $previous, bool $created): string
    {
        $names = $visit->relationships->map(fn (Relationship $person) => $person->displayName())->implode(', ');
        $count = (int) Plan::query()->withVisitStats()->find($plan->id)->visits_count;

        $message = ($created ? "Plan creado y visita registrada: \"{$plan->title}\"" : "Visita registrada: \"{$plan->title}\"")
            ." el {$visit->visited_on->toDateString()} con {$names}. ";
        $message .= 'Llevan '.Plan::visitsLabel($count);

        if ($previous) {
            $message .= '; la anterior fue '.Carbon::parse($previous)->startOfDay()->locale('es')->diffForHumans($visit->visited_on, ['parts' => 1, 'syntax' => Carbon::DIFF_RELATIVE_TO_OTHER]).' ('.substr($previous, 0, 10).')';
        }

        if ($visit->relationships->count() === 1) {
            $withPerson = (int) Plan::query()->withVisitStats($visit->relationships->first()->id)->find($plan->id)->visits_count;
            $message .= "; con {$names} van ".Plan::visitsLabel($withPerson);
        }

        return $message.". (id de la visita: {$visit->id})";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('Id del plan, si se conoce.'),
            'plan_title' => $schema->string()
                ->description('Nombre del lugar o plan. Ej. "Heladería Popsy". Se busca por coincidencia exacta y luego parcial.'),
            'contact_names' => $schema->array()
                ->items($schema->string())
                ->description('Personas con las que fue, por nombre, apodo o alias. Ej. ["Ali"].')
                ->required(),
            'visited_on' => $schema->string()
                ->description('Fecha de la visita en formato YYYY-MM-DD. Por defecto hoy; no puede ser futura. Convierte expresiones como "ayer" o "el sábado" a fecha.'),
            'comment' => $schema->string()
                ->description('Comentario opcional sobre cómo estuvo.'),
            'create_if_missing' => $schema->boolean()
                ->description('true para crear el plan si no existe y registrar la visita en el mismo paso.'),
            'type' => $schema->string()
                ->enum(array_keys(Plan::TYPES))
                ->description('Tipo del plan, solo si se crea. Por defecto place.'),
            'country_code' => $schema->string()->description('Código ISO de dos letras del país, por ejemplo CO.'),
            'city_id' => $schema->integer()->description('Identificador GeoNames de la ciudad. Requiere country_code.'),
            'city' => $schema->string()
                ->description('Ciudad del plan, solo si se crea.'),
            'circle_names' => $schema->array()
                ->items($schema->string())
                ->description('Círculos del plan, solo si se crea.'),
        ];
    }
}
