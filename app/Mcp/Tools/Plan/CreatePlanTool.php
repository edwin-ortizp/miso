<?php

namespace App\Mcp\Tools\Plan;

use App\Actions\SavePlan;
use App\Mcp\Tools\Plan\Concerns\ResolvesPlanTargets;
use App\Models\Plan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Guarda un plan, lugar, actividad o experiencia (restaurante, viaje, concierto…) y con qué círculos (Pareja, Familia, Amigos…) o personas se puede hacer. Consulta list-circles-tool para los nombres de círculos y list-plans-tool para no duplicar planes.')]
class CreatePlanTool extends Tool
{
    use ResolvesPlanTargets;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(Plan::TYPES))],
            'category' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:scheduled_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'circle_names' => ['nullable', 'array'],
            'circle_names.*' => ['string'],
            'contact_names' => ['nullable', 'array'],
            'contact_names.*' => ['string'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*' => ['url:http,https', 'max:2048'],
            'image_urls' => ['nullable', 'array', 'max:12'],
            'image_urls.*' => ['url:http,https', 'max:2048'],
        ]);

        $user = Auth::user();
        $title = trim($data['title']);
        $city = isset($data['city']) ? trim($data['city']) : null;

        $duplicate = $user->plans()
            ->whereRaw('lower(title) = ?', [mb_strtolower($title)])
            ->when($city, fn ($query) => $query->whereRaw('lower(city) = ?', [mb_strtolower($city)]))
            ->first();

        if ($duplicate) {
            return Response::error("Ya existe el plan {$this->describePlan($duplicate)}. Usa update-plan-tool para agregarle círculos, personas o enlaces.");
        }

        $circleIds = $this->resolveCircleIds($data['circle_names'] ?? []);
        if ($circleIds instanceof Response) {
            return $circleIds;
        }

        $contactIds = $this->resolveContactIds($data['contact_names'] ?? []);
        if ($contactIds instanceof Response) {
            return $contactIds;
        }

        $plan = new Plan;
        $plan->user_id = $user->id;

        SavePlan::handle($plan, [
            'title' => $title,
            'type' => $data['type'] ?? 'place',
            'category' => $data['category'] ?? null,
            'city' => $city ?: null,
            'address' => $data['address'] ?? null,
            'scheduled_on' => $data['scheduled_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $circleIds, $contactIds, collect($data['links'] ?? [])->map(fn (string $url) => ['url' => $url])->all(), $data['image_urls'] ?? []);

        return Response::text("Plan creado: {$this->describePlan($plan)}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Nombre del lugar, actividad o experiencia. Ej. "Heladería Popsy".')
                ->required(),
            'type' => $schema->string()
                ->enum(array_keys(Plan::TYPES))
                ->description('Tipo de plan: restaurant (Restaurante), dessert (Postres), place (Lugar), museum (Museo), activity (Actividad), cinema (Cine), concert (Concierto), event (Evento), trip (Viaje), nature (Naturaleza), experience (Experiencia). Por defecto place.'),
            'category' => $schema->string()
                ->description('Categoría libre. Ej. "Gastronomía", "Aire libre".'),
            'city' => $schema->string()
                ->description('Ciudad donde se hace el plan.'),
            'address' => $schema->string()
                ->description('Dirección, si se conoce.'),
            'scheduled_on' => $schema->string()
                ->description('Fecha del plan en formato YYYY-MM-DD, solo si tiene una fecha concreta (concierto, evento).'),
            'ends_on' => $schema->string()
                ->description('Fecha final en formato YYYY-MM-DD para eventos con ventana limitada.'),
            'notes' => $schema->string()
                ->description('Notas del plan.'),
            'circle_names' => $schema->array()
                ->items($schema->string())
                ->description('Círculos con los que se puede hacer el plan, por nombre exacto de list-circles-tool. Ej. ["Pareja", "Amigos"].'),
            'contact_names' => $schema->array()
                ->items($schema->string())
                ->description('Personas concretas con las que se quiere hacer, por nombre, apodo o alias.'),
            'links' => $schema->array()
                ->items($schema->string())
                ->description('Enlaces de referencia (Instagram, TikTok, Google Maps, web).'),
            'image_urls' => $schema->array()
                ->items($schema->string())
                ->description('URLs directas de imágenes del plan; no se descargan. La primera es la principal.'),
        ];
    }
}
