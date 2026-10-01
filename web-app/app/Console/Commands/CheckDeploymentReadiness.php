<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'mix7:deploy:check';

    protected $description = 'Verifica requisitos básicos antes de operar a plataforma em hospedagem.';

    public function handle(): int
    {
        $databaseDriver = config('database.connections.'.config('database.default').'.driver');
        $supportedDatabase = in_array($databaseDriver, ['mysql', 'mariadb', 'pgsql'], true);
        $databaseReady = $supportedDatabase;
        if ($databaseReady) {
            try {
                DB::select('SELECT 1');
            } catch (Throwable) {
                $databaseReady = false;
            }
        }

        $configuredStoragePath = config('filesystems.disks.local.root');
        $storagePath = is_string($configuredStoragePath) ? realpath($configuredStoragePath) : false;
        $publicPath = realpath(public_path());
        $privateStorage = $storagePath !== false && $publicPath !== false
            && is_dir($storagePath) && is_writable($storagePath)
            && ! str_starts_with(
                strtolower($storagePath.DIRECTORY_SEPARATOR),
                strtolower(rtrim($publicPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR),
            );
        $appUrlScheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
        $checks = [
            ['Ambiente de produção', app()->environment('production')],
            ['Debug desativado', config('app.debug') === false],
            ['Chave de aplicação configurada', filled(config('app.key'))],
            ['URL pública usa HTTPS', $appUrlScheme === 'https'],
            ['Banco PostgreSQL ou MariaDB selecionado', $supportedDatabase],
            ['Banco selecionado acessível', $databaseReady],
            ...($databaseDriver === 'pgsql' ? [['TLS do PostgreSQL obrigatório', in_array(config('database.connections.'.config('database.default').'.sslmode'), ['require', 'verify-ca', 'verify-full'], true)]] : []),
            ['Anexos privados e graváveis', $privateStorage],
            ['Cache do framework gravável', is_dir(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache'))],
            ['Cookie de sessão protegido', config('session.secure') === true
                && config('session.http_only') === true
                && in_array(config('session.same_site'), ['lax', 'strict'], true)],
        ];

        $failed = false;
        foreach ($checks as [$label, $passed]) {
            $this->components->twoColumnDetail($label, $passed ? '<fg=green>OK</>' : '<fg=red>FALHOU</>');
            $failed = $failed || ! $passed;
        }

        $warnings = [];
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $warnings[] = 'E-mail permanece no transporte local; convites e recuperação de senha não serão entregues.';
        }
        if (config('queue.default') !== 'database') {
            $warnings[] = 'Fila de banco não está ativa; processamento assíncrono de IA e agendamentos não operam.';
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($failed) {
            $this->newLine();
            $this->error('Pré-implantação reprovada. Corrija as verificações acima; valores secretos não são exibidos.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Verificações obrigatórias passaram. Confirme Cron, migrations, backup e envio real de e-mail separadamente.');

        return self::SUCCESS;
    }
}
