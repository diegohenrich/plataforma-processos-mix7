<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupArchive;
use Illuminate\Console\Command;

class RestoreBackup extends Command
{
    protected $signature = 'mix7:backup:restore {archive : Arquivo ZIP criptografado} {--destination= : Nova pasta privada, ainda inexistente}';

    protected $description = 'Restaura um backup Mix7 validado em uma pasta privada nova, sem substituir o banco ativo.';

    public function handle(BackupArchive $backup): int
    {
        try {
            $result = $backup->restore($this->argument('archive'), (string) $this->option('destination'));
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cópia restaurada e validada em pasta isolada. Banco: '.$result['database_entry']);
        $this->line('Nenhum banco configurado na aplicação foi substituído. Revise os arquivos antes de conectá-los.');

        return self::SUCCESS;
    }
}
