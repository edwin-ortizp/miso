<?php

namespace App\Mcp\Tools\Meal;

use App\Mcp\Support\McpOutput;
use App\Mcp\Tools\Meal\Concerns\InteractsWithMeals;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Meal\RecipeCalculator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista las recetas guardadas del usuario (nombre, tipo de comida, dificultad, tiempo, calorías, favorita, cuántas veces se ha planeado y cuándo fue la última). Con recipe_id o name devuelve la receta completa con ingredientes e instrucciones. Úsala para sugerir qué cocinar, armar el plan de la semana o responder sobre una receta.')]
class ListRecipesTool extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'recipe_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
            'meal_type' => ['nullable', 'string', Rule::in(array_keys(self::MEAL_TYPES))],
            'favorites_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (filled($data['recipe_id'] ?? null) || filled($data['name'] ?? null)) {
            $recipe = $this->resolveRecipe($data['recipe_id'] ?? null, $data['name'] ?? null);

            return $recipe instanceof Response ? $recipe : Response::structured($this->detail($recipe));
        }

        $recipes = Auth::user()->recipes()
            ->withCount('mealPlanItems')
            ->withMax(['mealPlanItems as last_planned_on' => fn ($query) => $query->join('meal_plan_entries', 'meal_plan_entries.id', '=', 'meal_plan_entry_items.meal_plan_entry_id')], 'meal_plan_entries.date')
            ->when(filled($data['search'] ?? null), function ($query) use ($data) {
                $like = '%'.addcslashes(trim($data['search']), '%_').'%';
                $query->where(fn ($match) => $match->where('name', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('recipeIngredients.shoppingItem', fn ($items) => $items->where('name', 'like', $like)));
            })
            ->when(filled($data['meal_type'] ?? null), fn ($query) => $query->where('meal_type', $data['meal_type']))
            ->when(! empty($data['favorites_only']), fn ($query) => $query->whereIn('id', app(\App\Services\Catalog\CatalogPicker::class)->favoriteIds('recipes') ?: ['-']))
            ->tap(fn ($query) => app(\App\Services\Catalog\CatalogPicker::class)->orderFavoritesFirst($query, 'recipes'))
            ->orderBy('name')
            ->limit($data['limit'] ?? 50)
            ->get();

        return Response::structured([
            'recipes' => $recipes->map(fn (Recipe $recipe) => McpOutput::compact([
                'id' => $recipe->id,
                'name' => $recipe->name,
                'meal_type' => $recipe->meal_type,
                'difficulty' => $recipe->difficulty,
                'prep_time_min' => $recipe->prep_time ? (int) $recipe->prep_time : null,
                'calories' => $recipe->nutrition['calories'] ?? null,
                'favorite' => $recipe->favorite ? true : null,
                'times_planned' => (int) $recipe->meal_plan_items_count ?: null,
                'last_planned_on' => $recipe->last_planned_on ? substr((string) $recipe->last_planned_on, 0, 10) : null,
            ]))->all(),
        ]);
    }

    private function detail(Recipe $recipe): array
    {
        $calculation = app(RecipeCalculator::class)->calculate($recipe);

        return McpOutput::compact([
            'id' => $recipe->id,
            'name' => $recipe->name,
            'description' => $recipe->description,
            'meal_type' => $recipe->meal_type,
            'difficulty' => $recipe->difficulty,
            'prep_time_min' => $recipe->prep_time ? (int) $recipe->prep_time : null,
            'servings' => (float) ($recipe->servings ?: 1),
            'nutrition' => $recipe->nutrition ?: null,
            'nutrition_source' => $recipe->nutrition_source,
            'cost_per_serving' => $calculation['cost_per_serving'],
            'missing_data' => array_values(array_unique([...$calculation['missing_cost'], ...$calculation['missing_nutrition']])) ?: null,
            'favorite' => $recipe->favorite ? true : null,
            'ingredients' => $recipe->recipeIngredients->map(fn (RecipeIngredient $ingredient) => trim(
                ($ingredient->quantity !== null ? (float) $ingredient->quantity.' ' : '')
                .($ingredient->unit ? $ingredient->unit.' ' : '')
                .($ingredient->shoppingItem?->name ?? 'Ingrediente')
                .($ingredient->notes ? " ({$ingredient->notes})" : '')
            ))->all(),
            'instructions' => $recipe->instructions,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe_id' => $schema->string()->description('Id de una receta para ver su detalle.'),
            'name' => $schema->string()->description('Nombre de una receta para ver su detalle.'),
            'search' => $schema->string()->description('Texto en el nombre, la descripción o un ingrediente (p. ej. "pollo").'),
            'meal_type' => $schema->string()->enum(array_keys(self::MEAL_TYPES))->description('Filtra por tipo de comida.'),
            'favorites_only' => $schema->boolean()->description('Solo recetas favoritas.'),
            'limit' => $schema->integer()->description('Máximo de recetas (por defecto 50).'),
        ];
    }
}
