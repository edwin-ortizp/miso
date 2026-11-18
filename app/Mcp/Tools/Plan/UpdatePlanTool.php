<?php

namespace App\Mcp\Tools\Plan;

use App\Actions\SavePlan;
use App\Mcp\Tools\Plan\Concerns\ResolvesPlanTargets;
use App\Models\Plan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Actualiza un plan existente: agrega o quita círculos, agrega personas o enlaces, y cambia sus datos, fecha o estado. Identifica el plan por plan_id o por título.')]
class UpdatePlanTool extends Tool
{
    use ResolvesPlanTargets;

    private const FIELDS = ['title', 'type', 'category', 'city', 'country_code', 'city_id', 'address', 'scheduled_on', 'ends_on', 'notes', 'status'];

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:160'],
            'new_title' => ['nullable', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(Plan::TYPES))],
            'category' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_on' => ['nullable', 'date_format:Y-m-d'],
            'clear_date' => ['nullable', 'boolean'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(Plan::STATUSES))],
            'add_circle_names' => ['nullable', 'array'],
            'add_circle_names.*' => ['string'],
            'remove_circle_names' => ['nullable', 'array'],
            'remove_circle_names.*' => ['string'],
            'add_contact_names' => ['nullable', 'array'],
            'add_contact_names.*' => ['string'],
            'add_links' => ['nullable', 'array', 'max:10'],
            'add_links.*' => ['url:http,https', 'max:2048'],
            'add_image_urls' => ['nullable', 'array', 'max:12'],
            'add_image_urls.*' => ['url:http,https', 'max:2048'],
            'main_image_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $plan = $this->resolvePlan($data['plan_id'] ?? null, $data['plan_id'] ?? null ? null : ($data['title'] ?? null));
        if ($plan instanceof Response) {
            return $plan;
        }

        $plan->load(['circles', 'relationships', 'links', 'images']);

        $added = $this->resolveCircleIds($data['add_circle_names'] ?? []);
        if ($added instanceof Response) {
            return $added;
        }

        $removed = $this->resolveCircleIds($data['remove_circle_names'] ?? []);
        if ($removed instanceof Response) {
            return $removed;
        }

        $people = $this->resolveContactIds($data['add_contact_names'] ?? []);
        if ($people instanceof Response) {
            return $people;
        }

        $attributes = collect($data)->only(array_diff(self::FIELDS, ['title']))->all();
        if (! empty($data['new_title'])) {
            $attributes['title'] = trim($data['new_title']);
        }
        if (! empty($data['clear_date'])) {
            $attributes['scheduled_on'] = null;
            $attributes['ends_on'] = null;
        }

        $hasChanges = $attributes !== [] || $added->isNotEmpty() || $removed->isNotEmpty() || $people->isNotEmpty() || ! empty($data['add_links']) || ! empty($data['add_image_urls']) || ! empty($data['main_image_url']);
        if (! $hasChanges) {
            return Response::error('No indicaste ningún cambio para el plan.');
        }

        SavePlan::handle(
            $plan,
            $attributes,
            $plan->circles->pluck('id')->merge($added)->diff($removed)->unique()->values(),
            $plan->relationships->pluck('id')->merge($people)->unique()->values(),
            empty($data['add_links']) ? null : $plan->links
                ->map(fn ($link) => ['url' => $link->url, 'label' => $link->label])
                ->merge(collect($data['add_links'])->reject(fn (string $url) => $plan->links->contains('url', $url))->map(fn (string $url) => ['url' => $url]))
                ->values()
                ->all(),
            $this->imagesAfterUpdate($plan, $data),
        );

        return Response::text("Plan actualizado: {$this->describePlan($plan->refresh())}.");
    }

    /** @return array<int, string>|null */
    private function imagesAfterUpdate(Plan $plan, array $data): ?array
    {
        if (empty($data['add_image_urls']) && empty($data['main_image_url'])) {
            return null;
        }

        $urls = $plan->images->pluck('url')->merge($data['add_image_urls'] ?? []);

        if (! empty($data['main_image_url'])) {
            $urls = collect([$data['main_image_url']])->merge($urls->reject(fn (string $url) => $url === $data['main_image_url']));
        }

        return $urls->unique()->values()->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('Id del plan. Prioritario sobre title.'),
            'title' => $schema->string()
                ->description('Título del plan a buscar si no se conoce el id.'),
            'new_title' => $schema->string()
                ->description('Nuevo título del plan.'),
            'type' => $schema->string()
                ->enum(array_keys(Plan::TYPES))
                ->description('Nuevo tipo de plan.'),
            'category' => $schema->string()->description('Nueva categoría.'),
            'country_code' => $schema->string()->description('Código ISO de dos letras del país, por ejemplo CO.'),
            'city_id' => $schema->integer()->description('Identificador GeoNames de la ciudad. Requiere country_code.'),
            'city' => $schema->string()->description('Nueva ciudad.'),
            'address' => $schema->string()->description('Nueva dirección.'),
            'scheduled_on' => $schema->string()->description('Nueva fecha en formato YYYY-MM-DD.'),
            'clear_date' => $schema->boolean()->description('true para quitar la fecha del plan.'),
            'ends_on' => $schema->string()->description('Nueva fecha final en formato YYYY-MM-DD.'),
            'notes' => $schema->string()->description('Nuevas notas.'),
            'status' => $schema->string()
                ->enum(array_keys(Plan::STATUSES))
                ->description('Nuevo estado: pending, scheduled, done o archived.'),
            'add_circle_names' => $schema->array()->items($schema->string())
                ->description('Círculos que se agregan al plan.'),
            'remove_circle_names' => $schema->array()->items($schema->string())
                ->description('Círculos que se quitan del plan.'),
            'add_contact_names' => $schema->array()->items($schema->string())
                ->description('Personas que se agregan al plan.'),
            'add_links' => $schema->array()->items($schema->string())
                ->description('Enlaces que se agregan al plan.'),
            'add_image_urls' => $schema->array()->items($schema->string())
                ->description('URLs de imágenes que se agregan al final; no se descargan.'),
            'main_image_url' => $schema->string()
                ->description('URL de la imagen que pasa a ser la principal (se agrega si no estaba).'),
        ];
    }
}
