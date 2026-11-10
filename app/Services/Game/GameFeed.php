<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use Illuminate\Support\Collection;

/**
 * Convierte los eventos nuevos del juego en avisos para la persona (CHG-034) y
 * decide si el momento merece una celebración. Solo lee: nunca otorga nada.
 */
class GameFeed
{
    public const MAX_EVENTS = 50;

    /**
     * @return array{last_id: int, toasts: array<int, array<string, mixed>>, celebrate: bool}
     */
    public function since(int $userId, int $lastId): array
    {
        $events = GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('id', '>', $lastId)
            ->orderBy('id')->limit(self::MAX_EVENTS)->get();

        if ($events->isEmpty()) {
            return ['last_id' => $lastId, 'toasts' => [], 'celebrate' => false];
        }

        $companions = $this->companionProfiles($events);
        $toasts = [];
        $celebrate = false;
        $teamGroups = [];
        $seenMissions = [];

        foreach ($events as $event) {
            $effect = $event->effect ?? [];

            switch ($event->kind) {
                case GameEvent::KIND_ACTIVITY:
                    if (! ($effect['ball'] ?? false)) {
                        break;
                    }
                    $celebrate = true;
                    $toasts[] = ($effect['special'] ?? false)
                        ? ['type' => 'goal', 'icon' => 'bi-trophy-fill', 'title' => '¡Meta cumplida!', 'detail' => $this->stripPrefix($event->source_label, 'Meta cumplida: '), 'action' => 'ball']
                        : ['type' => 'ball', 'icon' => null, 'title' => '¡Ganaste una ball!', 'detail' => $event->source_label, 'action' => 'ball'];
                    break;

                case GameEvent::KIND_XP_GAINED:
                    if (! ($effect['team'] ?? false)) {
                        break;
                    }
                    $group = (string) ($effect['activity_event_id'] ?? $event->id);
                    $teamGroups[$group] ??= ['label' => $this->stripPrefix($event->source_label, 'Equipo de la semana · '), 'members' => [], 'consistency' => (bool) ($effect['consistency'] ?? false)];
                    $companion = $companions[$effect['companion_id'] ?? 0] ?? ['name' => 'Tu Pokémon', 'image' => null];
                    $teamGroups[$group]['members'][] = [
                        'name' => $companion['name'],
                        'xp' => (int) ($effect['xp'] ?? 0),
                        'affinity' => (bool) ($effect['affinity'] ?? false),
                        'image' => $companion['image'],
                    ];
                    break;

                case CompanionProgress::KIND_LEVEL_UP:
                    $celebrate = true;
                    $name = $companions[$effect['companion_id'] ?? 0]['name'] ?? 'Tu Pokémon';
                    $toasts[] = ['type' => 'level', 'icon' => 'bi-arrow-up-circle-fill', 'title' => "¡{$name} subió al nivel ".($effect['to'] ?? '').'!', 'detail' => null, 'action' => null];
                    break;

                case CompanionProgress::KIND_EVOLVED:
                    $celebrate = true;
                    $from = GameSpecies::find($effect['from_species_id'] ?? 0)?->name ?? 'Tu Pokémon';
                    $to = GameSpecies::find($effect['to_species_id'] ?? 0)?->name ?? '';
                    $toasts[] = ['type' => 'evolution', 'icon' => 'bi-lightning-charge-fill', 'title' => "¡{$from} evolucionó a {$to}!", 'detail' => null, 'action' => null];
                    break;

                case GameEvent::KIND_MISSION_REWARD:
                    $key = ($effect['mission_id'] ?? '').':'.($effect['stage'] ?? '');
                    if (isset($seenMissions[$key])) {
                        break;
                    }
                    $seenMissions[$key] = true;
                    $title = $this->stripPrefix($event->source_label, 'Misión: ');
                    if (($effect['stage'] ?? null) === 'full') {
                        $celebrate = true;
                        $toasts[] = ['type' => 'mission', 'icon' => 'bi-flag-fill', 'title' => '¡Misión cumplida!', 'detail' => $title, 'action' => 'ball'];
                    } else {
                        $toasts[] = ['type' => 'mission', 'icon' => 'bi-flag', 'title' => 'Misión: '.$title, 'detail' => '¡Ball recibida!', 'action' => 'ball'];
                    }
                    break;
            }
        }

        foreach ($teamGroups as $group) {
            // Constancia (CHG-042): el aviso nombra la fuente; nunca celebra por sí solo.
            $plain = implode(' · ', array_map(
                fn (array $member) => $member['name'].' +'.$member['xp'].($member['affinity'] ? ' (afinidad)' : ''),
                $group['members']
            ));
            // La foto de cada integrante (CHG-050) va en `members`; `detail` sigue siendo el
            // texto plano que leen las pruebas y los lectores de pantalla.
            $toasts[] = [
                'type' => 'xp',
                'icon' => 'bi-stars',
                'title' => 'Tu equipo ganó experiencia',
                'detail' => $group['consistency'] ? $group['label'].' · '.$plain : $plain,
                'note' => $group['consistency'] ? $group['label'] : null,
                'members' => $group['members'],
                'action' => null,
            ];
        }

        return ['last_id' => (int) $events->last()->id, 'toasts' => $toasts, 'celebrate' => $celebrate];
    }

    /** @return array<int, array{name: string, image: ?string}> companion_id => nombre e imagen de su especie actual */
    private function companionProfiles(Collection $events): array
    {
        $ids = $events->map(fn ($event) => $event->effect['companion_id'] ?? null)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $images = app(SpeciesImageProvider::class);

        return GameCompanion::withoutGlobalScopes()->whereIn('id', $ids)->with('species')->get()
            ->mapWithKeys(fn (GameCompanion $companion) => [$companion->id => [
                'name' => $companion->species?->name ?? 'Tu Pokémon',
                'image' => $companion->species ? $images->url($companion->species) : null,
            ]])->all();
    }

    private function stripPrefix(?string $text, string $prefix): ?string
    {
        return $text !== null && str_starts_with($text, $prefix) ? substr($text, strlen($prefix)) : $text;
    }
}
