<?php

namespace App\Observers\Game;

use App\Models\Game\GameDiscovery;
use App\Services\Game\Albums;
use Illuminate\Support\Facades\DB;
use Throwable;

/** CHG-078: cada especie nueva (por ball o por evolución) revisa si completa un álbum. */
class AlbumObserver
{
    public function created(GameDiscovery $discovery): void
    {
        DB::afterCommit(function () use ($discovery): void {
            try {
                app(Albums::class)->awardIfComplete((int) $discovery->user_id, (int) $discovery->species_id);
            } catch (Throwable $exception) {
                report($exception); // El juego nunca debe impedir abrir una ball o evolucionar.
            }
        });
    }
}
