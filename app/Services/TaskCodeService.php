<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TaskCodeService
{
    public function lock(int $userId): void
    {
        if (! DB::table('users')->where('id', $userId)->lockForUpdate()->first()) {
            throw new InvalidArgumentException('La tarea necesita un propietario válido.');
        }
    }

    public function globalPrefix(int $userId): string
    {
        $settings = DB::table('module_settings')->where('user_id', $userId)->where('module', 'tasks')->value('settings');

        return (json_decode($settings ?? '{}', true) ?? [])['code_prefix'] ?? 'MSO';
    }

    public function validatePrefix(int $userId, string $prefix, ?string $categoryId = null, bool $global = false): string
    {
        $prefix = strtoupper(trim($prefix));
        if (! preg_match('/^[A-Z]{2,5}$/D', $prefix)) {
            throw new InvalidArgumentException('El prefijo debe tener entre 2 y 5 letras A–Z.');
        }
        $taken = DB::table('task_categories')->where('user_id', $userId)->where('code_prefix', $prefix)
            ->when($categoryId, fn ($q) => $q->where('id', '!=', $categoryId))->exists();
        if ($taken || (! $global && $this->globalPrefix($userId) === $prefix)) {
            throw new InvalidArgumentException('El prefijo ya está en uso.');
        }

        return $prefix;
    }

    public function suggest(int $userId, string $name): string
    {
        $letters = strtoupper(preg_replace('/[^a-z]/', '', strtolower(Str::ascii($name))));
        $base = match (strtolower(trim($name))) {
            'siigo' => 'SGX', 'gesthor' => 'GES', 'certmind' => 'CER',
            default => substr(str_pad($letters, 3, 'X'), 0, 3),
        };
        $used = DB::table('task_categories')->where('user_id', $userId)->whereNotNull('code_prefix')->pluck('code_prefix')->all();
        $used[] = $this->globalPrefix($userId);
        if (! in_array($base, $used, true)) {
            return $base;
        }
        for ($i = 0; $i < 26 * 26; $i++) {
            $candidate = $base.chr(65 + intdiv($i, 26)).chr(65 + $i % 26);
            if (! in_array($candidate, $used, true)) {
                return $candidate;
            }
        }
        throw new InvalidArgumentException('No quedan sugerencias disponibles; elige otro prefijo.');
    }

    public function prefixFor(int $userId, ?string $key): string
    {
        if ($key === null || $key === '') {
            return $this->globalPrefix($userId);
        }
        $category = DB::table('task_categories')->where('user_id', $userId)->where('key', $key)->first();
        if (! $category) {
            throw new InvalidArgumentException('La categoría de la tarea no pertenece a su propietario.');
        }
        if (! $category->code_prefix) {
            $category->code_prefix = $this->suggest($userId, $category->name);
            DB::table('task_categories')->where('id', $category->id)->update(['code_prefix' => $category->code_prefix]);
        }

        return $category->code_prefix;
    }

    /** Caller holds the user lock and includes the task insert in the same transaction. */
    public function next(int $userId, ?string $category): string
    {
        $prefix = $this->prefixFor($userId, $category);
        $query = DB::table('task_code_counters')->where('user_id', $userId)->where('prefix', $prefix);
        $last = $query->value('last_number');
        if ($last === null) {
            DB::table('task_code_counters')->insert(['user_id' => $userId, 'prefix' => $prefix, 'last_number' => 0]);
            $last = 0;
        }
        $next = $last + 1;
        $query->update(['last_number' => $next]);

        return $prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function resolve(int $userId, string $identifier): ?Task
    {
        $identifier = trim($identifier);

        return Task::withoutGlobalScopes()->where('user_id', $userId)
            ->where(fn ($q) => $q->where('id', $identifier)->orWhere('code', strtoupper($identifier)))->first();
    }
}
