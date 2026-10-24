<?php

/** Explicit integration harness; only accepts a disposable notification database. */
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Task;
use App\Services\Notifications\CreateNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$database = $argv[1] ?? '';
if (! preg_match('/^miso_notifications_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('An explicit disposable miso_notifications_test_* database is required.');
}
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database,
    'database.connections.mysql.url' => null, 'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => $argv[2] ?? 3307]);
DB::purge('mysql');
if (DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Database isolation check failed.');
}
if (($argv[3] ?? '') === 'schema') {
    $migration = require __DIR__.'/../../database/migrations/2026_10_24_000001_create_notification_tables.php';
    $before = count(DB::select('SHOW TABLES'));
    $migration->down();
    if (count(DB::select('SHOW TABLES')) !== $before - 2) {
        throw new RuntimeException('Rollback did not remove exactly two tables.');
    }
    $migration->up();
    if (count(DB::select('SHOW TABLES')) !== $before) {
        throw new RuntimeException('Migration did not restore exactly two tables.');
    }
    echo "PASS: MySQL migration down/up preserves all other tables.\n";
    exit;
}
if (($argv[3] ?? '') === 'worker') {
    $user = User::findOrFail($argv[4]);
    while (microtime(true) < (float) $argv[7]) {
        usleep(10000);
    }
    $task = Task::withoutGlobalScopes()->where('user_id', $user->id)->findOrFail($argv[6]);
    echo json_encode(['created' => app(CreateNotification::class)->create($user, $task, 'reminder', 'concurrent-key', 'Concurrent', 'Test', now()) !== null]);
    exit;
}

$user = User::factory()->create(['email' => 'notification-concurrency-'.Str::random(12).'@example.test']);
try {
    $task = new Task(['title' => 'Concurrent source']);
    $task->user_id = $user->id;
    $task->save();
    $run = function (string $operation, array $keys) use ($user, $database, $argv): array {
        $children = [];
        $start = microtime(true) + 2;
        foreach ($keys as $key) {
            $process = proc_open([PHP_BINARY, __FILE__, $database, $argv[2] ?? '3307', 'worker', (string) $user->id, $operation, $key, (string) $start],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, options: ['bypass_shell' => true]);
            fclose($pipes[0]);
            $children[] = [$process, $pipes];
        }
        $results = [];
        foreach ($children as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Concurrency worker failed: '.$error);
            }
            $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    $created = $run('create', array_fill(0, 4, $task->id));
    if (array_sum(array_column($created, 'created')) !== 1 || UserNotification::where('user_id', $user->id)->count() !== 1) {
        throw new RuntimeException('Concurrent deduplication failed.');
    }
    $task->delete();
    echo "PASS: four simultaneous producers created exactly one notification.\n";
} finally {
    $user->delete();
}
