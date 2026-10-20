<?php

namespace App\Livewire\Meal;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShoppingItem;
use App\Services\Meal\RecipeCalculator;
use App\Services\Meal\RecipeIngredientData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Recetas')]
class MealRecipes extends Component
{
    use WithManagementCard;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'type', history: true, keep: true)]
    public string $mealTypeFilter = '';

    #[Url(as: 'difficulty', history: true, keep: true)]
    public string $difficultyFilter = '';

    #[Url(as: 'fav', history: true)]
    public bool $favoriteFilter = false;

    public bool $showForm = false;
    public ?string $editingId = null;

    // Form fields
    public string $name = '';
    public string $description = '';
    public string $difficulty = 'facil';
    public ?int $prepTime = null;
    public $servings = 1;
    public string $mealType = 'comida';
    public string $instructions = '';
    public $nutritionCalories = null;
    public $nutritionProtein = null;
    public $nutritionCarbs = null;
    public $nutritionFat = null;
    public string $nutritionSource = 'manual';
    public bool $favorite = false;
    public array $ingredients = [];

    public array $mealTypes = [
        'desayuno' => 'Desayuno',
        'comida' => 'Onces (mañana)',
        'almuerzo' => 'Almuerzo',
        'merienda' => 'Merienda (tarde)',
        'cena' => 'Cena',
    ];

    public array $difficulties = [
        'facil' => 'Fácil',
        'medio' => 'Medio',
        'dificil' => 'Difícil',
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedMealTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedDifficultyFilter()
    {
        $this->resetPage();
    }

    public function updatedFavoriteFilter()
    {
        $this->resetPage();
    }

    public function openForm(?string $id = null)
    {
        $this->resetValidation();

        if ($id) {
            $recipe = Recipe::with('recipeIngredients.shoppingItem')->find($id);
            if (!$recipe) return;

            $this->editingId = $recipe->id;
            $this->name = $recipe->name;
            $this->description = $recipe->description ?? '';
            $this->difficulty = $recipe->difficulty ?? 'facil';
            $this->prepTime = $recipe->prep_time;
            $this->servings = $recipe->servings ?: 1;
            $this->nutritionSource = $recipe->nutrition_source ?? 'manual';
            $this->mealType = $recipe->meal_type ?? 'comida';
            $this->instructions = $recipe->instructions ?? '';
            $this->favorite = $recipe->favorite;
            $this->nutritionCalories = $recipe->nutrition['calories'] ?? null;
            $this->nutritionProtein = $recipe->nutrition['protein'] ?? null;
            $this->nutritionCarbs = $recipe->nutrition['carbs'] ?? null;
            $this->nutritionFat = $recipe->nutrition['fat'] ?? null;
            $this->ingredients = $recipe->recipeIngredients->map(fn($i) => [
                'shopping_item_id' => $i->shopping_item_id,
                'name' => $i->shoppingItem?->name ?? '',
                'quantity' => $i->quantity,
                'unit' => $i->unit ?? '',
                'notes' => $i->notes ?? '',
            ])->toArray();
        } else {
            $this->editingId = null;
            $this->name = '';
            $this->description = '';
            $this->difficulty = 'facil';
            $this->prepTime = null;
            $this->servings = 1;
            $this->nutritionSource = 'manual';
            $this->mealType = 'comida';
            $this->instructions = '';
            $this->favorite = false;
            $this->nutritionCalories = null;
            $this->nutritionProtein = null;
            $this->nutritionCarbs = null;
            $this->nutritionFat = null;
            $this->ingredients = [];
        }

        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function addIngredient()
    {
        $this->ingredients[] = ['shopping_item_id' => null, 'name' => '', 'quantity' => '', 'unit' => '', 'notes' => ''];
    }

    public function removeIngredient(int $index)
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function save()
    {
        $this->ingredients = array_values(array_filter(
            $this->ingredients,
            fn (array $ingredient) => collect($ingredient)->contains(
                fn ($value) => $value !== null && trim((string) $value) !== ''
            )
        ));

        $this->validate([
            'name' => 'required|string|max:255',
            'difficulty' => 'required|in:facil,medio,dificil',
            'mealType' => 'required|in:desayuno,almuerzo,comida,merienda,cena',
            'servings' => 'required|numeric|gt:0|max:999',
            'nutritionCalories' => 'nullable|numeric|min:0',
            'nutritionProtein' => 'nullable|numeric|min:0',
            'nutritionCarbs' => 'nullable|numeric|min:0',
            'nutritionFat' => 'nullable|numeric|min:0',
            'ingredients' => 'array',
            'ingredients.*.name' => 'nullable|required_without:ingredients.*.shopping_item_id|string|max:255',
            'ingredients.*.shopping_item_id' => 'nullable|string',
            'ingredients.*.quantity' => 'required|numeric|gt:0|max:999999999.999',
            'ingredients.*.unit' => 'nullable|string|max:50',
            'ingredients.*.notes' => 'nullable|string|max:1000',
        ], [
            'ingredients.*.name.required_without' => 'El ingrediente es obligatorio.',
            'ingredients.*.quantity.required' => 'La cantidad es obligatoria.',
            'ingredients.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'ingredients.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
            'ingredients.*.quantity.max' => 'La cantidad es demasiado grande.',
        ]);

        $nutrition = array_filter([
            'calories' => $this->nutritionCalories,
            'protein' => $this->nutritionProtein,
            'carbs' => $this->nutritionCarbs,
            'fat' => $this->nutritionFat,
        ], fn($v) => $v !== null && $v !== '');

        $data = [
            'name' => trim($this->name),
            'description' => $this->description ?: null,
            'difficulty' => $this->difficulty,
            'prep_time' => $this->prepTime,
            'servings' => $this->servings,
            'meal_type' => $this->mealType,
            'instructions' => $this->instructions ?: null,
            'nutrition' => $nutrition ?: null,
            'favorite' => $this->favorite,
        ];

        if ($this->editingId) {
            $recipe = Recipe::find($this->editingId);
            if (!$recipe) return;
            $recipe->update($data);
        } else {
            $recipe = Recipe::create($data);
        }

        // Sync ingredients
        $recipe->recipeIngredients()->delete();
        foreach ($this->ingredients as $ingredient) {
            if (empty($ingredient['name']) && empty($ingredient['shopping_item_id'])) continue;

            $shoppingItemId = $ingredient['shopping_item_id'];
            $item = $shoppingItemId ? ShoppingItem::find($shoppingItemId) : null;
            if (! $item && ! empty($ingredient['name'])) {
                $item = RecipeIngredientData::product($ingredient['name'], $ingredient['unit'] ?? null);
            }

            if ($item) {
                [$quantity, $unit] = RecipeIngredientData::quantity($item, (float) $ingredient['quantity'], $ingredient['unit'] ?? null);
                $recipe->recipeIngredients()->create([
                    'shopping_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'notes' => $ingredient['notes'] ?: null,
                ]);
            }
        }

        // Si todos los ingredientes tienen nutrición, la receta la calcula sola; si no, queda la manual.
        app(RecipeCalculator::class)->refreshNutrition($recipe->refresh());

        $this->closeForm();
    }

    public function toggleFavorite(string $id)
    {
        $recipe = Recipe::find($id);
        if ($recipe) {
            $recipe->update(['favorite' => !$recipe->favorite]);
        }
    }

    public function delete(string $id)
    {
        if (\App\Models\MealPreparation::where('user_id', auth()->id())->where('recipe_id', $id)->exists()) {
            $this->addError('name', 'Esta receta tiene preparaciones registradas y debe conservarse para su historial.');
            return;
        }
        Recipe::where('id', $id)->delete();
    }

    protected function filterProperties(): array
    {
        return ['favoriteFilter', 'mealTypeFilter', 'difficultyFilter'];
    }

    public function render()
    {
        $recipes = Recipe::query()
            ->when($this->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($this->mealTypeFilter, fn($q, $t) => $q->where('meal_type', $t))
            ->when($this->difficultyFilter, fn($q, $d) => $q->where('difficulty', $d))
            ->when($this->favoriteFilter, fn($q) => $q->where('favorite', true))
            ->withCount('recipeIngredients')
            ->orderByDesc('updated_at')
            ->paginate($this->perPage());

        $shoppingItems = ShoppingItem::orderBy('name')->get(['id', 'name', 'base_unit']);

        // Costo por porción en la tabla; el detalle de faltantes solo se calcula para la receta abierta.
        $calculator = app(RecipeCalculator::class);
        $recipes->getCollection()->load(['recipeIngredients.shoppingItem' => fn ($query) => $query->withOffers()]);
        $costs = $recipes->getCollection()->mapWithKeys(fn (Recipe $recipe) => [$recipe->id => $calculator->calculate($recipe)]);
        $editingCalculation = $this->editingId ? $calculator->calculate(Recipe::find($this->editingId) ?? new Recipe()) : null;

        return view('livewire.meal.meal-recipes', [
            'recipes' => $recipes,
            'shoppingItems' => $shoppingItems,
            'costs' => $costs,
            'editingCalculation' => $editingCalculation,
        ]);
    }
}
