<?php

namespace App\Services\Learning;

use App\Models\LearningFormation;
use App\Models\ModuleSetting;
use Illuminate\Validation\ValidationException;

/**
 * CHG-141: nombres de lo que se estudia según el tipo de formación. Defaults en `config/learning.php` y
 * renombrado por persona y por tipo en `module_settings` (módulo `learning`, clave `vocabulary`).
 * Solo cambia lo que se muestra: las claves internas de tipos y contenidos son estables.
 */
class LearningVocabulary
{
    /** @var array<int, array<string, array<string, array{singular?: string, plural?: string}>>> */
    private array $cache = [];

    /** @return array<string, string> */
    public function typeLabels(): array
    {
        return (array) config('learning.types', LearningFormation::TYPES);
    }

    public function typeLabel(?string $type): string
    {
        return $this->typeLabels()[$type ?? ''] ?? 'Formación';
    }

    /** Familia de contenido de un tipo de formación: academic, course o personal. */
    public function family(?string $type): string
    {
        return in_array($type, ['course', 'personal'], true) ? $type : 'academic';
    }

    /** @return list<string> Tipos de contenido que declara la familia, en orden. */
    public function kinds(?string $type): array
    {
        return array_keys((array) config('learning.units.'.$this->family($type), []));
    }

    /** Término por defecto de un tipo de contenido dentro de un tipo de formación. */
    public function defaultTerm(?string $type, string $kind): array
    {
        $term = config('learning.units.'.$this->family($type).'.'.$kind) ?? config('learning.fallback.'.$kind) ?? [$kind, $kind.'s', 'm'];

        return ['singular' => $term[0], 'plural' => $term[1], 'gender' => $term[2] ?? 'm'];
    }

    /** @return array{singular: string, plural: string, gender: string} */
    public function term(int $userId, ?string $type, string $kind): array
    {
        $term = $this->defaultTerm($type, $kind);
        $custom = $this->overrides($userId)[$type ?? ''][$kind] ?? [];
        if (filled($custom['singular'] ?? null)) {
            $term['singular'] = $custom['singular'];
            $term['gender'] = self::guessGender($custom['singular']);
        }
        if (filled($custom['plural'] ?? null)) {
            $term['plural'] = $custom['plural'];
        }

        return $term;
    }

    public function singular(int $userId, ?string $type, string $kind): string
    {
        return $this->term($userId, $type, $kind)['singular'];
    }

    public function plural(int $userId, ?string $type, string $kind): string
    {
        return $this->term($userId, $type, $kind)['plural'];
    }

    /** «Semestre», «Materia»… con mayúscula inicial. */
    public function label(int $userId, ?string $type, string $kind): string
    {
        return mb_strtoupper(mb_substr($this->singular($userId, $type, $kind), 0, 1)).mb_substr($this->singular($userId, $type, $kind), 1);
    }

    /** «un semestre» / «una materia». */
    public function indefinite(int $userId, ?string $type, string $kind): string
    {
        $term = $this->term($userId, $type, $kind);

        return ($term['gender'] === 'f' ? 'una ' : 'un ').$term['singular'];
    }

    /** «Este semestre» / «Esta materia». */
    public function demonstrative(int $userId, ?string $type, string $kind): string
    {
        $term = $this->term($userId, $type, $kind);

        return ($term['gender'] === 'f' ? 'Esta ' : 'Este ').$term['singular'];
    }

    /** «las materias» / «los temas». */
    public function definitePlural(int $userId, ?string $type, string $kind): string
    {
        $term = $this->term($userId, $type, $kind);

        return ($term['gender'] === 'f' ? 'las ' : 'los ').$term['plural'];
    }

    /** «Agregar semestre». */
    public function addLabel(int $userId, ?string $type, string $kind): string
    {
        return 'Agregar '.$this->singular($userId, $type, $kind);
    }

    /**
     * «semestres y materias» / «etapas, cursos y temas».
     *
     * @param  list<string>  $kinds
     */
    public function plurals(int $userId, ?string $type, array $kinds): string
    {
        $words = array_map(fn ($kind) => $this->plural($userId, $type, $kind), $kinds);
        $last = array_pop($words);

        return $words ? implode(', ', $words).' y '.$last : (string) $last;
    }

    /**
     * Términos efectivos de todos los tipos de formación para la pantalla de ajustes.
     *
     * @return array<string, array<string, array{singular: string, plural: string}>>
     */
    public function all(int $userId): array
    {
        $all = [];
        foreach (array_keys($this->typeLabels()) as $type) {
            foreach ($this->kinds($type) as $kind) {
                $term = $this->term($userId, $type, $kind);
                $all[$type][$kind] = ['singular' => $term['singular'], 'plural' => $term['plural']];
            }
        }

        return $all;
    }

    /**
     * Guarda los términos que difieren de los valores por defecto (vacío = valor por defecto).
     *
     * @param  array<string, array<string, array{singular?: string, plural?: string}>>  $input
     */
    public function save(int $userId, array $input): void
    {
        $max = (int) config('learning.term_max', 30);
        $errors = [];
        $stored = [];
        foreach (array_keys($this->typeLabels()) as $type) {
            $seen = [];
            foreach ($this->kinds($type) as $kind) {
                $default = $this->defaultTerm($type, $kind);
                $row = [];
                foreach (['singular', 'plural'] as $field) {
                    $value = trim((string) ($input[$type][$kind][$field] ?? ''));
                    if ($value === '') {
                        $value = $default[$field];
                    }
                    if (mb_strlen($value) > $max) {
                        $errors["vocabulary.{$type}.{$kind}.{$field}"] = "Usa máximo {$max} caracteres.";

                        continue;
                    }
                    if (preg_match('/[<>]/u', $value)) {
                        $errors["vocabulary.{$type}.{$kind}.{$field}"] = 'Escribe solo letras y números.';

                        continue;
                    }
                    $value = mb_strtolower($value);
                    if ($value !== $default[$field]) {
                        $row[$field] = $value;
                    }
                    if ($field === 'singular') {
                        if (isset($seen[$value])) {
                            $errors["vocabulary.{$type}.{$kind}.singular"] = 'Ese nombre ya lo usa otra parte de esta formación.';
                        }
                        $seen[$value] = true;
                    }
                }
                if ($row) {
                    $stored[$type][$kind] = $row;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $this->write($userId, $stored);
    }

    public function reset(int $userId): void
    {
        $this->write($userId, []);
    }

    /** @return array<string, array<string, array{singular?: string, plural?: string}>> */
    private function overrides(int $userId): array
    {
        return $this->cache[$userId] ??= (array) (ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'learning')->first()?->settings['vocabulary'] ?? []);
    }

    private function write(int $userId, array $vocabulary): void
    {
        $setting = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => 'learning']);
        $setting->user_id = $userId;
        $settings = $setting->settings ?? [];
        if ($vocabulary) {
            $settings['vocabulary'] = $vocabulary;
        } else {
            unset($settings['vocabulary']);
        }
        $setting->settings = $settings;
        $setting->save();
        unset($this->cache[$userId]);
    }

    /** Género gramatical probable de un término propio (m o f). */
    public static function guessGender(string $word): string
    {
        $word = mb_strtolower(trim($word));
        $masculineInA = ['tema', 'mapa', 'día', 'problema', 'programa', 'sistema', 'idioma', 'diploma', 'esquema', 'módulo'];
        if (in_array($word, $masculineInA, true)) {
            return 'm';
        }
        if (preg_match('/(a|ión|dad|tad|tud|umbre|ez)$/u', $word)) {
            return 'f';
        }

        return 'm';
    }
}
