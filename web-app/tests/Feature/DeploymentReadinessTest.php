<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    public function test_deployment_check_passes_required_configuration_without_printing_secrets(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:synthetic-test-key-not-a-secret',
            'app.url' => 'https://mix7.example.test',
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'filesystems.disks.local.root' => storage_path('app/private'),
            'mail.default' => 'log',
            'queue.default' => 'sync',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);

        $this->artisan('mix7:deploy:check')
            ->expectsOutputToContain('Verificações obrigatórias passaram')
            ->expectsOutputToContain('E-mail permanece no transporte local')
            ->expectsOutputToContain('Fila de banco não está ativa')
            ->doesntExpectOutput('base64:synthetic-test-key-not-a-secret')
            ->assertExitCode(0);
    }

    public function test_deployment_check_fails_unsafe_settings_and_never_prints_secret_values(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.key' => 'synthetic-key-value',
            'app.url' => 'http://mix7.example.test',
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'filesystems.disks.local.root' => public_path(),
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $this->artisan('mix7:deploy:check')
            ->expectsOutputToContain('Pré-implantação reprovada')
            ->expectsOutputToContain('Anexos privados e graváveis')
            ->doesntExpectOutput('synthetic-key-value')
            ->assertExitCode(1);
    }
}
