<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para desarrollo local: una cuenta ficticia con una rutina de ejemplo.
 * Nunca se ejecuta en producción ni se ofrece a cuentas reales.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.test'],
            ['name' => 'Cuenta de demostración', 'password' => 'password'],
        );

        foreach ($this->positive() as $habit) {
            $user->habitDefinitions()->firstOrCreate(['name' => $habit['name'], 'time_of_day' => $habit['time_of_day']], $habit);
        }
        foreach ($this->negative() as $habit) {
            $user->negativeHabitDefinitions()->firstOrCreate(['name' => $habit['name']], $habit);
        }
    }

    private function positive(): array
    {
        return [
            ['name' => 'Tomar Agua (mañana)', 'icon' => '💧', 'time_of_day' => 'morning', 'goal_duration' => '5 min', 'base_time' => '05:50'],
            ['name' => 'Ejercicio', 'icon' => '🎾', 'time_of_day' => 'morning', 'goal_duration' => '40 min', 'base_time' => '07:00'],
            ['name' => 'Ducha fria', 'icon' => '🚿', 'time_of_day' => 'morning', 'goal_duration' => '10 min', 'base_time' => '08:15'],
            ['name' => 'Desayuno', 'icon' => '🍳', 'time_of_day' => 'morning', 'goal_duration' => '30 min', 'base_time' => '08:30'],
            ['name' => 'Lavarme los dientes', 'icon' => '🪥', 'time_of_day' => 'morning', 'goal_duration' => '2 min', 'base_time' => '09:00'],
            ['name' => 'Seda Dental', 'icon' => '🦷', 'time_of_day' => 'morning', 'goal_duration' => '2 min', 'base_time' => '09:05'],
            ['name' => 'Tender la cama', 'icon' => '🛏️', 'time_of_day' => 'morning', 'goal_duration' => '3 min', 'base_time' => '09:10'],
            ['name' => 'Lavar loza del desayuno', 'icon' => '🧽', 'time_of_day' => 'morning', 'goal_duration' => '10 min', 'base_time' => '09:15'],
            ['name' => 'Aplicarme bloqueador solar', 'icon' => '☀️', 'time_of_day' => 'morning', 'goal_duration' => '5 min', 'base_time' => '09:30'],
            ['name' => 'Almuerzo', 'icon' => '🍽️', 'time_of_day' => 'afternoon', 'goal_duration' => '60 min', 'base_time' => '12:30'],
            ['name' => 'Siesta', 'icon' => '🛌', 'time_of_day' => 'afternoon', 'goal_duration' => '15 min', 'base_time' => '13:00'],
            ['name' => 'Lavarme los dientes', 'icon' => '🪥', 'time_of_day' => 'afternoon', 'goal_duration' => '2 min', 'base_time' => '13:15'],
            ['name' => 'Seda Dental', 'icon' => '🦷', 'time_of_day' => 'afternoon', 'goal_duration' => '2 min', 'base_time' => '13:30'],
            ['name' => 'Tomar agua (tarde)', 'icon' => '💧', 'time_of_day' => 'afternoon', 'goal_duration' => '5 min', 'base_time' => '15:00'],
            ['name' => 'Cena', 'icon' => '🍽️', 'time_of_day' => 'night', 'goal_duration' => '30 min', 'base_time' => '19:45'],
            ['name' => 'Lavarme los dientes', 'icon' => '🪥', 'time_of_day' => 'night', 'goal_duration' => '2 min', 'base_time' => '20:00'],
            ['name' => 'Seda Dental', 'icon' => '🦷', 'time_of_day' => 'night', 'goal_duration' => '2 min', 'base_time' => '20:15'],
            ['name' => 'Llevar el diario', 'icon' => '📓', 'time_of_day' => 'night', 'goal_duration' => '10 min', 'base_time' => '20:30'],
            ['name' => 'Botar algo que no sirva', 'icon' => '🗑️', 'time_of_day' => 'night', 'goal_duration' => '10 min', 'base_time' => '20:45'],
            ['name' => 'Organizar la cocina', 'icon' => '🍴', 'time_of_day' => 'night', 'goal_duration' => '15 min', 'base_time' => '21:00'],
            ['name' => 'Lectura', 'icon' => '📚', 'time_of_day' => 'night', 'goal_duration' => '5 min', 'base_time' => '21:15'],
            ['name' => 'Lista de pendientes', 'icon' => '📝', 'time_of_day' => 'night', 'goal_duration' => '15 min', 'base_time' => '21:30'],
            ['name' => 'Alistar la ropa para mañana', 'icon' => '👕', 'time_of_day' => 'night', 'goal_duration' => '5 min', 'base_time' => '21:45'],
        ];
    }

    private function negative(): array
    {
        return [
            ['name' => 'Saltarse comidas', 'icon' => '🍽️', 'category' => 'health'],
            ['name' => 'Dormir poco', 'icon' => '😴', 'category' => 'health'],
            ['name' => 'No hacer ejercicio', 'icon' => '🏃', 'category' => 'health'],
            ['name' => 'Comer en exceso', 'icon' => '🫃🏻', 'category' => 'health'],
            ['name' => 'Comida chatarra', 'icon' => '🍔', 'category' => 'health'],
            ['name' => 'Exceso de azúcar', 'icon' => '🍭', 'category' => 'health'],
            ['name' => 'Masturbación', 'icon' => '🍆', 'category' => 'health'],
            ['name' => 'Procrastinar', 'icon' => '⏰', 'category' => 'productivity'],
            ['name' => 'Distracciones', 'icon' => '🎯', 'category' => 'productivity'],
            ['name' => 'Multitarea', 'icon' => '🔄', 'category' => 'productivity'],
            ['name' => 'Desorganización', 'icon' => '📋', 'category' => 'productivity'],
            ['name' => 'Exceso de trabajo (Workaholic)', 'icon' => '🍽️', 'category' => 'productivity'],
            ['name' => 'Aislamiento', 'icon' => '🚶', 'category' => 'social'],
            ['name' => 'Conflictos', 'icon' => '💢', 'category' => 'social'],
            ['name' => 'No escuchar', 'icon' => '👂', 'category' => 'social'],
            ['name' => 'Dejar en visto', 'icon' => '👀', 'category' => 'social'],
            ['name' => 'Sobrepensar cosas', 'icon' => '😰', 'category' => 'emotional'],
            ['name' => 'Negatividad / Pesimismo', 'icon' => '🌧️', 'category' => 'emotional'],
            ['name' => 'Ser grosero/estresarme', 'icon' => '🤬', 'category' => 'emotional'],
            ['name' => 'Celos compulsivos', 'icon' => '💁🏻‍♂️', 'category' => 'emotional'],
            ['name' => 'Gastos impulsivos', 'icon' => '💳', 'category' => 'finance'],
            ['name' => 'No ahorrar', 'icon' => '💰', 'category' => 'finance'],
            ['name' => 'Más Deudas', 'icon' => '📉', 'category' => 'finance'],
            ['name' => 'Exceso de Redes sociales', 'icon' => '📱', 'category' => 'digital'],
            ['name' => 'Exceso de Videojuegos', 'icon' => '🎮', 'category' => 'digital'],
            ['name' => 'Exceso de Netflix', 'icon' => '📺', 'category' => 'digital'],
            ['name' => 'Desperdiciar agua', 'icon' => '💧', 'category' => 'environment'],
            ['name' => 'No reciclar', 'icon' => '♻️', 'category' => 'environment'],
            ['name' => 'Consumo excesivo', 'icon' => '🛍️', 'category' => 'environment'],
        ];
    }
}
