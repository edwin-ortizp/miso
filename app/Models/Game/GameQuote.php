<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

/**
 * Frase del integrante del día (CHG-043). `user_id` null = semilla global,
 * siempre elegible; las del director vencen a los `game.quotes.max_age_days`.
 */
class GameQuote extends Model
{
    public const SOURCE_SEED = 'seed';

    public const SOURCE_DIRECTOR = 'director';

    public const SEED = [
        'Paso a paso también se llega lejos.',
        'Hoy cuenta lo que hagas, no lo que faltó ayer.',
        'Un pequeño avance sigue siendo avance.',
        'Descansar también es parte del camino.',
        'Lo que empiezas hoy ya es tuyo.',
        'Tu ritmo es el correcto.',
        'Cada tarea cerrada deja espacio para algo bueno.',
        'Respira, elige una cosa y empieza por ahí.',
        'Las grandes colecciones empiezan con una ball.',
        'Cuidarte también es progresar.',
        'Hoy es buen día para algo sencillo.',
        'Lo constante vence a lo perfecto.',
        'Un vaso de agua y seguimos.',
        'Celebra lo pequeño: suma más de lo que parece.',
        'Tu equipo crece contigo.',
        'Nada se pierde por empezar despacio.',
        'Un minuto de foco ya cuenta.',
        'La curiosidad también es energía.',
        'Hoy puedes hacerlo a tu manera.',
        'Lo que hiciste ayer sigue sumando.',
        'Elige lo que te acerque a lo que quieres.',
        'Una buena pausa prepara un buen avance.',
        'Tus hábitos son semillas; déjalas crecer.',
        'Ordenar una cosa ordena un poco la mente.',
        'Hoy también hay algo que descubrir.',
        'Escríbelo, hazlo pequeño y empieza.',
        'Tu mejor versión no tiene prisa.',
        'Cada día trae una oportunidad nueva.',
        'Lo importante es volver, no no haberse ido.',
        'Vamos juntos, un paso más.',
    ];

    protected $fillable = ['user_id', 'text', 'source', 'week_start'];

    protected function casts(): array
    {
        return ['week_start' => 'date'];
    }
}
