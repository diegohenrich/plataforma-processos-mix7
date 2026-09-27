<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiAgentRun;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AiQueueScheduleTest extends TestCase
{
    public function test_database_queue_runs_one_job_per_minute_without_overlapping(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();
        $this->assertCount(1, $events);

        config(['queue.default' => 'sync']);
        $this->assertFalse($events[0]->filtersPass(app()));

        config(['queue.default' => 'database']);
        $this->assertTrue($events[0]->filtersPass(app()));
        $this->assertStringContainsString('queue:work --once --timeout=100 --tries=1', $events[0]->command);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame(3, $events[0]->expiresAt);
        $this->assertSame(100, (new ProcessAiAgentRun(1, 'encrypted'))->timeout);
    }
}
