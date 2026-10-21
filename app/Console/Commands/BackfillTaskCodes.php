<?php

namespace App\Console\Commands;

use App\Services\TaskCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTaskCodes extends Command
{
    protected $signature = 'tasks:backfill-codes {--dry-run : Validate and count without writing}';

    protected $description = 'Asigna prefijos y códigos ausentes, conservando fechas y códigos existentes. Suspender escrituras durante su ejecución.';

    public function handle(TaskCodeService $codes): int
    {
        $invalid = DB::table('tasks as t')->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('u.id')->count();
        $invalid += DB::table('task_categories as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->whereNull('u.id')->count();
        $orphans = DB::table('tasks as t')->leftJoin('task_categories as c', function ($join) {
            $join->on('c.user_id', '=', 't.user_id')->on('c.key', '=', 't.category');
        })->whereNotNull('t.category')->where('t.category', '!=', '')->whereNull('c.id')->count();
        if ($invalid || $orphans) {
            $this->error("Sin cambios: {$invalid} propietarios inválidos; {$orphans} tareas con categoría huérfana.");

            return self::FAILURE;
        }
        $pending = DB::table('tasks')->whereNull('code')->count();
        $this->info("Tareas pendientes de numerar: {$pending}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        foreach (DB::table('users')->orderBy('id')->pluck('id') as $userId) {
            DB::transaction(function () use ($userId, $codes) {
                $codes->lock($userId);
                foreach (DB::table('task_categories')->where('user_id', $userId)->orderBy('created_at')->orderBy('id')->get() as $category) {
                    $codes->prefixFor($userId, $category->key);
                }
                $setting = DB::table('module_settings')->where('user_id', $userId)->where('module', 'tasks')->first();
                $settings = json_decode($setting->settings ?? '{}', true) ?? [];
                $settings['code_prefix'] ??= 'MSO';
                DB::table('module_settings')->updateOrInsert(['user_id' => $userId, 'module' => 'tasks'], ['settings' => json_encode($settings)]);
                // Recover counters from existing codes, including a resumed backfill.
                foreach (DB::table('tasks')->where('user_id', $userId)->whereNotNull('code')->pluck('code') as $code) {
                    if (! preg_match('/^([A-Z]{2,5})-([0-9]+)$/D', $code, $match)) {
                        throw new \RuntimeException('Código existente inválido: '.$code);
                    }
                    $counter = DB::table('task_code_counters')->where('user_id', $userId)->where('prefix', $match[1]);
                    $last = $counter->value('last_number');
                    if ($last === null) {
                        DB::table('task_code_counters')->insert(['user_id' => $userId, 'prefix' => $match[1], 'last_number' => (int) $match[2]]);
                    } elseif ($last < (int) $match[2]) {
                        $counter->update(['last_number' => (int) $match[2]]);
                    }
                }
                foreach (DB::table('tasks')->where('user_id', $userId)->whereNull('code')->orderBy('created_at')->orderBy('id')->get(['id', 'category']) as $task) {
                    DB::table('tasks')->where('user_id', $userId)->where('id', $task->id)->update(['code' => $codes->next($userId, $task->category)]);
                }
            }, 3);
        }
        $this->info('Numeración terminada.');

        return self::SUCCESS;
    }
}
