<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Experience\PersonalExperience;
use App\Support\DefaultExerciseTypes;
use App\Support\DefaultMoodStates;
use App\Support\DefaultTaskCategories;
use App\Support\DefaultVehicleExpenseCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ], [
            'name.required' => 'Ingresa tu nombre.',
            'name.string' => 'Ingresa un nombre válido.',
            'name.max' => 'Tu nombre puede tener hasta 255 caracteres.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.max' => 'El correo puede tener hasta 255 caracteres.',
            'email.unique' => 'Este correo ya está registrado. Inicia sesión con tu cuenta.',
            'password.required' => 'Crea una contraseña.',
            'password.string' => 'Ingresa una contraseña válida.',
            'password.min' => 'Usa una contraseña de 8 caracteres o más.',
            'password_confirmation.required' => 'Confirma tu contraseña.',
            'password_confirmation.string' => 'Ingresa una confirmación válida.',
            'password_confirmation.same' => 'Las contraseñas no coinciden. Revisa la confirmación.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $this->seedDefaultUserData($user);
            app(PersonalExperience::class)->begin((int) $user->id, true);

            return $user;
        });

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('onboarding');
    }

    private function seedDefaultUserData(User $user): void
    {
        DefaultExerciseTypes::createFor($user);

        $drinkTypes = [
            ['name' => 'Agua', 'hydration_factor' => 1.00, 'color' => '#3b82f6', 'icon' => 'Droplet', 'category' => 'water'],
            ['name' => 'Café', 'hydration_factor' => 0.70, 'color' => '#78350f', 'icon' => 'Coffee', 'category' => 'coffee'],
            ['name' => 'Té', 'hydration_factor' => 0.85, 'color' => '#84cc16', 'icon' => 'Coffee', 'category' => 'tea'],
            ['name' => 'Jugo', 'hydration_factor' => 0.80, 'color' => '#f97316', 'icon' => 'Apple', 'category' => 'juice'],
            ['name' => 'Gaseosa', 'hydration_factor' => 0.50, 'color' => '#a3a3a3', 'icon' => 'Wine', 'category' => 'soda'],
            ['name' => 'Leche', 'hydration_factor' => 0.85, 'color' => '#e5e5e5', 'icon' => 'Milk', 'category' => 'milk'],
            ['name' => 'Bebida Deportiva', 'hydration_factor' => 0.90, 'color' => '#06b6d4', 'icon' => 'Zap', 'category' => 'sports'],
            ['name' => 'Cerveza', 'hydration_factor' => 0.60, 'color' => '#fbbf24', 'icon' => 'Beer', 'category' => 'beer'],
            ['name' => 'Vino', 'hydration_factor' => 0.50, 'color' => '#dc2626', 'icon' => 'Wine', 'category' => 'wine'],
            ['name' => 'Batido', 'hydration_factor' => 0.80, 'color' => '#ec4899', 'icon' => 'Cup', 'category' => 'smoothie'],
        ];

        foreach ($drinkTypes as $type) {
            $user->drinkTypes()->create($type);
        }

        DefaultMoodStates::createFor($user);

        DefaultVehicleExpenseCategories::createFor($user);
        DefaultTaskCategories::createFor($user);
    }
}
