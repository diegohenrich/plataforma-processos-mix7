<?php

namespace App\Console\Commands;

use App\Services\TaskTimerHeartbeat;
use Illuminate\Console\Command;

class ExpireStaleTaskTimers extends Command
{
    protected $signature = 'mix7:timers:expire-stale';

    protected $description = 'Encerra cronômetros sem sinal recente e pausa as tarefas correspondentes.';

    public function handle(TaskTimerHeartbeat $heartbeat): int
    {
        $heartbeat->closeAllStale();

        $this->info('Cronômetros sem sinal foram conferidos.');

        return self::SUCCESS;
    }
}
