<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateNotifications extends Command
{
    protected $signature = 'notifications:generate {--user= : ID del propietario; omitido procesa todos}';

    protected $description = 'Genera las ocurrencias actuales de notificaciones, sin reconstruir días anteriores';

    public function handle(\App\Services\Notifications\GenerateNotifications $generator): int
    {
        $count = 0;
        User::query()->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))->eachById(function (User $user) use ($generator, &$count) {
            $count += $generator->run($user)['created'];
        });
        $this->info("Notificaciones creadas: {$count}");

        return self::SUCCESS;
    }
}
