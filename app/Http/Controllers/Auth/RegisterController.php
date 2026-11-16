<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ProvisionNewUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Experience\PersonalExperience;
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

            ProvisionNewUser::for($user);
            app(PersonalExperience::class)->begin((int) $user->id, true);

            return $user;
        });

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('onboarding');
    }
}
