<?php

namespace Tests\Unit;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkflowStatusTest extends TestCase
{
    public function test_demand_flow_requires_review_and_client_decision_before_delivery(): void
    {
        self::assertSame([DemandStatus::Planning], DemandStatus::Received->next());
        self::assertSame([DemandStatus::InternalReview], DemandStatus::InProgress->next());
        self::assertSame([DemandStatus::Adjustments, DemandStatus::Delivery], DemandStatus::ClientApproval->next());
        self::assertSame([], DemandStatus::Completed->next());
    }

    #[DataProvider('validTaskTransitions')]
    public function test_task_transition_is_allowed(TaskStatus $from, TaskStatus $to): void
    {
        self::assertContains($to, $from->next());
    }

    /** @return iterable<string, array{TaskStatus, TaskStatus}> */
    public static function validTaskTransitions(): iterable
    {
        yield 'start todo task' => [TaskStatus::Todo, TaskStatus::InProgress];
        yield 'pause running task' => [TaskStatus::InProgress, TaskStatus::Paused];
        yield 'complete running task' => [TaskStatus::InProgress, TaskStatus::Completed];
        yield 'resume paused task' => [TaskStatus::Paused, TaskStatus::InProgress];
        yield 'resume blocked task' => [TaskStatus::Blocked, TaskStatus::InProgress];
    }
}
