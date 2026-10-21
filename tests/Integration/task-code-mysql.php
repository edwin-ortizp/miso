<?php

// Run against a migrated disposable database named miso_task_code_test only.
// DB_CONNECTION=mysql DB_DATABASE=miso_task_code_test DB_PORT=<isolated port> php tests/Integration/task-code-mysql.php
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'miso_task_code_test') {
    throw new RuntimeException('Dedicated disposable MySQL miso_task_code_test required.');
}

if (($argv[1] ?? '') === '--worker') {
    [, , $userId, $mode, $gate] = $argv;
    $deadline = microtime(true) + 20;
    while (! is_file($gate)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout');
        }
        usleep(1000);
    }
    $user = User::findOrFail($userId);
    if ($mode === 'prefix') {
        TaskCategory::withoutGlobalScopes()->where('user_id', $userId)->where('key', 'siigo')->firstOrFail()->update(['code_prefix' => 'SGO']);
    } elseif ($mode === 'rollback') {
        try {
            DB::transaction(function () use ($user) {
                $user->tasks()->create(['title' => 'Rolled back', 'category' => 'siigo']);
                throw new RuntimeException('intentional rollback');
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'intentional rollback') {
                throw $e;
            }
        }
    } else {
        $user->tasks()->create(['title' => 'Concurrent', 'category' => 'siigo']);
    }
    exit(0);
}

$user = User::factory()->create();
$user->taskCategories()->create(['key' => 'siigo', 'name' => 'Siigo']);
$run = function (array $modes) use ($user): void {
    $gate = sys_get_temp_dir().'/miso-code-barrier-'.bin2hex(random_bytes(8));
    $workers = [];
    try {
        foreach ($modes as $mode) {
            $worker = new Process([PHP_BINARY, __FILE__, '--worker', (string) $user->id, $mode, $gate], base_path(), timeout: 45);
            $worker->start();
            $workers[] = $worker;
        }
        usleep(400000);
        touch($gate);
        foreach ($workers as $worker) {
            $worker->wait();
            if (! $worker->isSuccessful()) {
                throw new RuntimeException($worker->getErrorOutput().$worker->getOutput());
            }
        }
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        if (is_file($gate)) {
            unlink($gate);
        }
    }
};
$run(array_fill(0, 8, 'create'));
$run(['create', 'create', 'create', 'create', 'create', 'create', 'rollback', 'prefix']);
$tasks = $user->tasks()->get();
if ($tasks->count() !== 14 || $tasks->pluck('code')->unique()->count() !== 14) {
    throw new RuntimeException('Lost or duplicate task code');
}
foreach (DB::table('task_code_counters')->where('user_id', $user->id)->get() as $counter) {
    $numbers = $tasks->filter(fn ($task) => str_starts_with($task->code, $counter->prefix.'-'))
        ->map(fn ($task) => (int) explode('-', $task->code)[1])->sort()->values()->all();
    if ($numbers !== range(1, (int) $counter->last_number)) {
        throw new RuntimeException('Gap or consumed rolled-back code');
    }
}
echo json_encode(['concurrent_tasks' => 14, 'unique' => true, 'gapless' => true, 'prefix_change_and_rollback' => true]).PHP_EOL;
