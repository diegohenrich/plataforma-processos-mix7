<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --once --timeout=100 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(3)
    ->when(fn (): bool => config('queue.default') === 'database')
    ->description('Processa no máximo uma execução enfileirada de IA por minuto.');
