<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Catalogs\ModuleCatalogs;

/**
 * Datos iniciales de una cuenta nueva. Solo lo que usan los módulos iniciales
 * (Tareas); el resto de catálogos se siembra al abrir cada módulo (ModuleCatalogs).
 * Es idempotente: repetirla no duplica ni restaura nada.
 */
class ProvisionNewUser
{
    public static function for(User $user): void
    {
        ModuleCatalogs::ensure((int) $user->id, 'tasks');
    }
}
