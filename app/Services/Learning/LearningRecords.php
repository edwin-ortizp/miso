<?php

namespace App\Services\Learning;

use App\Models\LearningFormation;
use App\Models\LearningShortcut;
use App\Models\LearningUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** All Learning writes serialize on the owner, including empty-container checks. */
class LearningRecords
{
    public static function children(string $kind): array
    {
        return match ($kind) {
            'academic' => ['period', 'subject'], 'period' => ['subject'], 'subject', 'module' => ['topic'],
            'course' => ['module'], 'personal' => ['stage', 'course', 'topic'], 'stage' => ['course', 'topic'], default => [],
        };
    }

    public function formation(int $userId, string $id): LearningFormation
    {
        return LearningFormation::withoutGlobalScopes()->where('user_id', $userId)->findOrFail($id);
    }

    public function unit(int $userId, string $formation, string $id): LearningUnit
    {
        return LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formation)->findOrFail($id);
    }

    private function write(int $userId, callable $callback): mixed
    {
        return DB::transaction(function () use ($userId, $callback) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();

            return $callback();
        });
    }

    private function fields(array $data): array
    {
        $data['title'] = trim($data['title'] ?? '');
        foreach (['start_date', 'end_date', 'credits', 'category'] as $key) {
            if (($data[$key] ?? null) === '') {
                $data[$key] = null;
            }
        }

        return Validator::make($data, [
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(array_keys(LearningFormation::STATES))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...(! empty($data['start_date']) ? ['after_or_equal:start_date'] : [])],
        ], ['end_date.after_or_equal' => 'La fecha final no puede ser anterior al inicio.'])->validate();
    }

    public function saveFormation(int $userId, ?string $id, array $data): LearningFormation
    {
        return $this->write($userId, function () use ($userId, $id, $data) {
            $record = $id ? $this->formation($userId, $id) : new LearningFormation;
            $values = $this->fields($data);
            $data['category'] = ($data['category'] ?? '') === '' ? null : $data['category'];
            $extra = Validator::make($data, ['type' => ['required', Rule::in(array_keys(LearningFormation::TYPES))],
                'institution' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', Rule::exists('user_categories', 'key')->where('user_id', $userId)->where('domain', 'learning')]])->validate();
            if ($record->exists && $record->type !== $extra['type'] && $record->units()->withoutGlobalScopes()->exists()) {
                throw ValidationException::withMessages(['type' => 'El tipo se conserva porque esta formación ya tiene contenido organizado.']);
            }
            $record->fill($values + $extra);
            $record->user_id = $userId;
            $record->save();

            return $record;
        });
    }

    public function saveUnit(int $userId, string $formationId, ?string $id, ?string $parentId, string $kind, array $data): LearningUnit
    {
        return $this->write($userId, function () use ($userId, $formationId, $id, $parentId, $kind, $data) {
            $formation = $this->formation($userId, $formationId);
            $parent = $parentId ? $this->unit($userId, $formationId, $parentId) : null;
            $record = $id ? $this->unit($userId, $formationId, $id) : new LearningUnit;
            if ($record->exists && ($record->parent_id !== $parentId || $record->kind !== $kind)) {
                throw ValidationException::withMessages(['kind' => 'La ubicación y el tipo del contenido se conservan al editar.']);
            }
            if (! in_array($kind, self::children($parent?->kind ?? $formation->kind), true)) {
                throw ValidationException::withMessages(['kind' => 'Este tipo de contenido no corresponde a esta ubicación.']);
            }
            if (array_key_exists('semester', $data)) {
                $destination = $data['semester'] ?? '';
                $period = is_string($destination) && $destination !== ''
                    ? LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formationId)->where('kind', 'period')->find($destination)
                    : null;
                if (! $record->exists || $kind !== 'subject' || $formation->kind !== 'academic' || ($destination !== '' && ! $period)) {
                    throw ValidationException::withMessages(['semester' => 'Elige un semestre disponible de esta formación.']);
                }
                $parentId = $period?->id;
            }
            $values = $this->fields($data);
            $credits = Validator::make(['credits' => ($data['credits'] ?? '') === '' ? null : $data['credits']], ['credits' => ['nullable', 'numeric', 'between:0,100', 'multiple_of:0.5']])->validate()['credits'];
            if ($kind !== 'subject' && $credits !== null) {
                $vocabulary = app(LearningVocabulary::class);
                throw ValidationException::withMessages(['credits' => 'Solo '.$vocabulary->definitePlural($userId, $formation->type, 'subject').' admiten créditos.']);
            }
            if (! $record->exists) {
                $record->formation_id = $formationId;
                $record->kind = $kind;
            }
            if (! $record->exists || $record->parent_id !== $parentId) {
                $record->parent_id = $parentId;
                $record->position = 1 + (int) LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formationId)->where('parent_id', $parentId)->max('position');
            }
            $record->fill($values + ['credits' => $credits]);
            $record->user_id = $userId;
            $record->save();

            return $record;
        });
    }

    public function move(int $userId, string $formationId, string $id, int $direction): void
    {
        $this->write($userId, function () use ($userId, $formationId, $id, $direction) {
            abort_unless(in_array($direction, [-1, 1], true), 422);
            $record = $this->unit($userId, $formationId, $id);
            $siblings = LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formationId)->where('parent_id', $record->parent_id)->orderBy('position')->orderBy('id')->get();
            $index = $siblings->search(fn ($sibling) => $sibling->id === $id);
            $other = $siblings->get($index + $direction);
            if ($other) {
                [$siblings[$index], $siblings[$index + $direction]] = [$other, $record];
                foreach ($siblings as $position => $sibling) {
                    $sibling->position = $position + 1;
                    $sibling->save();
                }
            }
        });
    }

    public function delete(int $userId, string $formationId, ?string $unitId = null): void
    {
        $this->write($userId, function () use ($userId, $formationId, $unitId) {
            $record = $unitId ? $this->unit($userId, $formationId, $unitId) : $this->formation($userId, $formationId);
            $children = LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->where('formation_id', $formationId);
            if ($unitId) {
                $children->where('parent_id', $unitId);
            }
            if ($children->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Este elemento tiene contenido. Retíralo antes de eliminarlo.']);
            }
            LearningShortcut::withoutGlobalScopes()->where('user_id', $userId)->where('target_type', $unitId ? 'unit' : 'formation')->where('target_id', $record->id)->delete();
            $record->delete();
        });
    }

    public function pin(int $userId, string $type, string $id, bool $pinned): void
    {
        $this->write($userId, function () use ($userId, $type, $id, $pinned) {
            $this->target($userId, $type, $id);
            $query = LearningShortcut::withoutGlobalScopes()->where('user_id', $userId)->where('target_type', $type)->where('target_id', $id);
            if (! $pinned) {
                $query->delete();

                return;
            }
            if ($query->exists()) {
                return;
            }
            LearningShortcut::create(['user_id' => $userId, 'target_type' => $type, 'target_id' => $id,
                'position' => 1 + (int) LearningShortcut::withoutGlobalScopes()->where('user_id', $userId)->max('position')]);
        });
    }

    private function target(int $userId, string $type, string $id): LearningFormation|LearningUnit
    {
        abort_unless(in_array($type, ['formation', 'unit'], true), 404);

        return ($type === 'formation' ? LearningFormation::withoutGlobalScopes() : LearningUnit::withoutGlobalScopes())->where('user_id', $userId)->findOrFail($id);
    }

    /** Batch reads retain current names and distinguish identical titles by their complete origin. */
    public function shortcuts(int $userId): array
    {
        $pins = LearningShortcut::withoutGlobalScopes()->where('user_id', $userId)->orderBy('position')->get();
        if ($pins->isEmpty()) {
            return [];
        }
        $formations = LearningFormation::withoutGlobalScopes()->where('user_id', $userId)->get()->keyBy('id');
        $units = LearningUnit::withoutGlobalScopes()->where('user_id', $userId)->get()->keyBy('id');
        $items = [];
        foreach ($pins as $pin) {
            $target = $pin->target_type === 'formation' ? $formations->get($pin->target_id) : $units->get($pin->target_id);
            $formation = $pin->target_type === 'formation' ? $target : $formations->get($target?->formation_id);
            if (! $target || ! $formation) {
                continue;
            }
            $origin = [];
            $parent = $target instanceof LearningUnit ? $target->parent_id : null;
            $seen = [];
            while ($parent && ! isset($seen[$parent]) && ($ancestor = $units->get($parent))) {
                $seen[$parent] = true;
                array_unshift($origin, $ancestor->title);
                $parent = $ancestor->parent_id;
            }
            $items[] = ['id' => $target->id, 'type' => $pin->target_type, 'title' => $target->title,
                'label' => $target instanceof LearningUnit ? app(LearningVocabulary::class)->label($userId, $formation->type, $target->kind) : app(LearningVocabulary::class)->typeLabel($target->type),
                'origin' => $target instanceof LearningUnit ? implode(' / ', [$formation->title, ...$origin]) : 'Formación',
                'href' => $target instanceof LearningUnit ? route('learning.units.show', [$formation->id, $target->id]) : route('learning.show', $formation->id)];
        }

        return $items;
    }
}
