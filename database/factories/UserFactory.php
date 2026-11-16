<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * Las pruebas parten de una cuenta con los catálogos transversales que siempre tuvo el modelo
     * (círculos, categorías de compras y categorías por dominio). El resto de catálogos se siembra
     * por módulo (ModuleCatalogs); para una cuenta sin nada, usa User::create().
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            \App\Actions\SeedDefaultCircles::for($user);
            \App\Actions\SeedDefaultMealCategories::for($user);
            \App\Support\CategoryCatalog::seedFor($user->id);
        });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
