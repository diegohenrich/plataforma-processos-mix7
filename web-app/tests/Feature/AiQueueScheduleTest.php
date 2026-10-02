<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiAgentRun;
use App\Services\TaskTimerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AiQueueScheduleTest extends TestCase
{
    public function test_database_queue_runs_one_job_per_minute_without_overlapping(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();
        $this->assertCount(2, $events);
        $queueEvent = collect($events)->first(fn ($event): bool => str_contains($event->command, 'queue:work --once --timeout=100 --tries=1'));
        $timerEvent = collect($events)->first(fn ($event): bool => str_contains($event->command, 'mix7:timers:expire-stale'));
        $this->assertNotNull($queueEvent);
        $this->assertNotNull($timerEvent);

        config(['queue.default' => 'sync']);
        $this->assertFalse($queueEvent->filtersPass(app()));

        config(['queue.default' => 'database']);
        $this->assertTrue($queueEvent->filtersPass(app()));
        $this->assertStringContainsString('queue:work --once --timeout=100 --tries=1', $queueEvent->command);
        $this->assertTrue($queueEvent->withoutOverlapping);
        $this->assertSame(3, $queueEvent->expiresAt);
        $this->assertTrue($timerEvent->withoutOverlapping);
        $this->assertSame(3, $timerEvent->expiresAt);
        $this->assertSame(100, (new ProcessAiAgentRun(1, 'encrypted'))->timeout);
    }

    public function test_stale_timer_command_runs_the_expiration_service(): void
    {
        $heartbeat = $this->mock(TaskTimerHeartbeat::class);
        $heartbeat->shouldReceive('closeAllStale')->once();

        $this->artisan('mix7:timers:expire-stale')
            ->expectsOutput('Cronômetros sem sinal foram conferidos.')
            ->assertExitCode(0);
    }
}
