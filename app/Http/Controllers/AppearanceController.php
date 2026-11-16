<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppearanceController extends Controller
{
    /** Guarda la preferencia de apariencia del usuario autenticado. */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(User::THEME_PREFERENCES)],
        ]);

        $request->user()->update(['theme_preference' => $validated['theme']]);

        return response()->json(['theme' => $validated['theme']]);
    }
}
