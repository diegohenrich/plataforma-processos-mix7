<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoWorkspaceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_refused_outside_local_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV=local, SQLite e database/mix7-demo.sqlite');

        (new DemoWorkspaceSeeder)->run();
    }

    public function test_default_database_seed_does_not_create_a_shared_test_account(): void
    {
        (new DatabaseSeeder)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
