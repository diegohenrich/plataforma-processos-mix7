<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupArchive;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'mix7:backup:create {--directory= : Pasta privada onde o arquivo será salvo}';

    protected $description = 'Cria uma cópia criptografada do banco e dos anexos privados Mix7.';

    public function handle(BackupArchive $backup): int
    {
        try {
            $path = $backup->create($this->option('directory'));
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Backup AES-256 criado fora da pasta pública: '.$path);
        $this->line('A restauração exige a mesma APP_KEY e grava somente em uma pasta nova e isolada.');

        return self::SUCCESS;
    }
}
