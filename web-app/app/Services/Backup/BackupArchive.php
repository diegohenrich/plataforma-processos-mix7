<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use ZipArchive;

class BackupArchive
{
    public function create(?string $directory = null): string
    {
        $this->assertZipSupport();
        $password = $this->archivePassword();
        $directory = $this->privateDirectory($directory ?: storage_path('app/backups'));
        $temporaryDirectory = storage_path('framework/mix7-backups');

        File::ensureDirectoryExists($directory, 0700);
        File::ensureDirectoryExists($temporaryDirectory, 0700);

        $suffix = now()->format('Ymd-His').'-'.Str::lower(Str::random(8));
        $archivePath = $directory.DIRECTORY_SEPARATOR.'mix7-backup-'.$suffix.'.zip';
        $databasePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'database-'.$suffix;
        $zip = new ZipArchive;
        $opened = false;

        try {
            $database = $this->exportDatabase($databasePath);
            $databaseEntry = 'database/database.'.$database['extension'];
            $entries = [
                $databaseEntry => [
                    'path' => $databasePath,
                    'bytes' => filesize($databasePath),
                    'sha256' => hash_file('sha256', $databasePath),
                ],
            ];

            $configuredPrivateRoot = config('filesystems.disks.local.root');
            $privateRoot = is_string($configuredPrivateRoot) ? realpath($configuredPrivateRoot) : false;
            if ($privateRoot !== false && File::isDirectory($privateRoot)) {
                if ($this->isInside(public_path(), $privateRoot)) {
                    throw new RuntimeException('O disco de anexos privados aponta para a pasta pública; o backup foi recusado.');
                }

                $privateFileIndex = 0;
                foreach (File::allFiles($privateRoot) as $file) {
                    if ($file->isLink()) {
                        continue;
                    }

                    $relative = str_replace('\\', '/', $file->getRelativePathname());
                    $entry = sprintf('storage/private/%06d.bin', ++$privateFileIndex);
                    $entries[$entry] = [
                        'path' => $file->getPathname(),
                        'restore_path' => 'storage/private/'.$relative,
                        'bytes' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getPathname()),
                    ];
                }
            }

            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Não foi possível criar o arquivo de backup no destino informado.');
            }
            $opened = true;
            $zip->setPassword($password);

            foreach ($entries as $entry => $metadata) {
                if (! $zip->addFile($metadata['path'], $entry)
                    || ! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256)) {
                    throw new RuntimeException('Não foi possível incluir um arquivo protegido na cópia.');
                }
            }

            $manifest = [
                'format' => 'mix7-backup',
                'format_version' => 1,
                'created_at' => now()->toIso8601String(),
                'database_driver' => $database['driver'],
                'database_entry' => $databaseEntry,
                'encryption' => 'AES-256 using a key derived from APP_KEY',
                'entries' => collect($entries)->map(fn (array $metadata, string $entry): array => [
                    'path' => $metadata['restore_path'] ?? $entry,
                    'bytes' => $metadata['bytes'],
                    'sha256' => $metadata['sha256'],
                ])->all(),
            ];

            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
                || ! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) {
                throw new RuntimeException('Não foi possível incluir o manifesto protegido da cópia.');
            }

            $zip->close();
            $opened = false;
            @chmod($archivePath, 0600);

            return $archivePath;
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }

            if (is_file($archivePath)) {
                File::delete($archivePath);
            }

            throw $exception;
        } finally {
            if (is_file($databasePath)) {
                File::delete($databasePath);
            }
        }
    }

    /** @return array{database_driver: string, database_entry: string, entries: array<string, array{bytes: int, sha256: string}>} */
    public function restore(string $archivePath, string $destination): array
    {
        $this->assertZipSupport();
        $password = $this->archivePassword();
        $archivePath = realpath($archivePath) ?: throw new RuntimeException('O arquivo de backup informado não existe.');
        if (! is_file($archivePath)) {
            throw new RuntimeException('O arquivo de backup informado não é um arquivo.');
        }

        $target = $this->newPrivateDestination($destination);
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('O arquivo de backup está danificado ou não é um ZIP válido.');
        }

        $createdDestination = false;

        try {
            $zip->setPassword($password);
            $manifestJson = $zip->getFromName('manifest.json');
            if (! is_string($manifestJson)) {
                throw new RuntimeException('O arquivo não contém um manifesto válido ou foi protegido por outra APP_KEY.');
            }

            $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
            $this->validateManifest($manifest, $zip);

            if (! mkdir($target, 0700)) {
                throw new RuntimeException('Não foi possível criar a pasta isolada para restauração.');
            }
            $createdDestination = true;

            foreach ($manifest['entries'] as $entry => $metadata) {
                $outputPath = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $metadata['path']);
                File::ensureDirectoryExists(dirname($outputPath), 0700);
                $input = $zip->getStream($entry);
                $output = fopen($outputPath, 'xb');

                if (! is_resource($input) || ! is_resource($output)) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    throw new RuntimeException('Não foi possível extrair um arquivo para a pasta isolada.');
                }

                $hash = hash_init('sha256');
                $bytes = 0;
                while (! feof($input)) {
                    $chunk = fread($input, 1024 * 1024);
                    if ($chunk === false) {
                        throw new RuntimeException('Falha ao ler o arquivo de backup.');
                    }
                    if ($chunk === '') {
                        continue;
                    }
                    $written = fwrite($output, $chunk);
                    if ($written !== strlen($chunk)) {
                        throw new RuntimeException('Falha ao gravar o arquivo restaurado.');
                    }
                    hash_update($hash, $chunk);
                    $bytes += $written;
                }

                fclose($input);
                fclose($output);
                @chmod($outputPath, 0600);

                if ($bytes !== $metadata['bytes'] || ! hash_equals($metadata['sha256'], hash_final($hash))) {
                    throw new RuntimeException('A verificação de integridade da cópia falhou.');
                }
            }

            $databasePath = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $manifest['database_entry']);
            if (str_ends_with($databasePath, '.sqlite')) {
                $this->assertHealthySqlite($databasePath);
            }

            return [
                'database_driver' => $manifest['database_driver'],
                'database_entry' => $manifest['database_entry'],
                'entries' => $manifest['entries'],
            ];
        } catch (\Throwable $exception) {
            if ($createdDestination) {
                File::deleteDirectory($target);
            }

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    private function exportDatabase(string $path): array
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => $this->exportSqlite($path),
            'mysql', 'mariadb' => $this->exportMysql($path),
            default => throw new RuntimeException('Backup suportado apenas para SQLite, MySQL ou MariaDB.'),
        };
    }

    private function exportSqlite(string $path): array
    {
        $source = (string) config('database.connections.'.config('database.default').'.database');
        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('O backup SQLite exige um arquivo de banco existente; bancos em memória não podem ser copiados.');
        }

        $quotedPath = str_replace("'", "''", $path);
        DB::connection()->statement("VACUUM INTO '{$quotedPath}'");

        return ['driver' => 'sqlite', 'extension' => 'sqlite'];
    }

    private function exportMysql(string $path): array
    {
        $pdo = DB::connection()->getPdo();
        $handle = fopen($path, 'xb');
        if (! is_resource($handle)) {
            throw new RuntimeException('Não foi possível preparar o arquivo temporário do banco.');
        }

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();

        try {
            $this->write($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $pdo->query('SHOW FULL TABLES');

            while (($table = $tables->fetch(PDO::FETCH_NUM)) !== false) {
                if (($table[1] ?? null) !== 'BASE TABLE') {
                    continue;
                }

                $tableName = (string) $table[0];
                $quotedTable = $this->quoteIdentifier($tableName);
                $create = $pdo->query('SHOW CREATE TABLE '.$quotedTable)->fetch(PDO::FETCH_ASSOC);
                $createSql = $create['Create Table'] ?? array_values($create ?? [])[1] ?? null;
                if (! is_string($createSql)) {
                    throw new RuntimeException('Não foi possível ler a definição de uma tabela do MariaDB.');
                }
                $this->write($handle, "-- Table: {$tableName}\n{$createSql};\n");

                $columns = $pdo->query('SHOW FULL COLUMNS FROM '.$quotedTable)->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_map(fn (array $column): string => (string) $column['Field'], $columns);
                $columnTypes = array_column($columns, 'Type', 'Field');
                if ($columnNames === []) {
                    continue;
                }

                $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columnNames));
                $rows = $pdo->query('SELECT * FROM '.$quotedTable);
                $batch = [];

                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $values = [];
                    foreach ($columnNames as $columnName) {
                        $values[] = $this->sqlValue($pdo, $row[$columnName] ?? null, (string) ($columnTypes[$columnName] ?? ''));
                    }
                    $batch[] = '('.implode(', ', $values).')';

                    if (count($batch) === 100) {
                        $this->write($handle, 'INSERT INTO '.$quotedTable.' ('.$quotedColumns.") VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $this->write($handle, 'INSERT INTO '.$quotedTable.' ('.$quotedColumns.") VALUES\n".implode(",\n", $batch).";\n");
                }

                $this->write($handle, "\n");
            }

            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($handle);
            File::delete($path);
            throw $exception;
        }

        fclose($handle);

        return ['driver' => DB::connection()->getDriverName(), 'extension' => 'sql'];
    }

    private function validateManifest(mixed $manifest, ZipArchive $zip): void
    {
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'mix7-backup' || ($manifest['format_version'] ?? null) !== 1
            || ! in_array($manifest['database_driver'] ?? null, ['sqlite', 'mysql', 'mariadb'], true)
            || ! in_array($manifest['database_entry'] ?? null, ['database/database.sqlite', 'database/database.sql'], true)
            || ! isset($manifest['entries']) || ! is_array($manifest['entries'])
            || ! isset($manifest['entries'][$manifest['database_entry']])) {
            throw new RuntimeException('O manifesto não corresponde a um backup Mix7 compatível.');
        }

        $databaseExtension = $manifest['database_driver'] === 'sqlite' ? '.sqlite' : '.sql';
        if (! str_ends_with($manifest['database_entry'], $databaseExtension)) {
            throw new RuntimeException('O tipo de banco do manifesto não corresponde ao arquivo incluído.');
        }

        $expectedNames = ['manifest.json'];
        $restorePaths = [];
        foreach ($manifest['entries'] as $entry => $metadata) {
            if (! is_string($entry) || ! is_array($metadata)) {
                throw new RuntimeException('O manifesto contém um caminho ou checksum inválido.');
            }

            $restorePath = $metadata['path'] ?? null;
            $isDatabase = $entry === $manifest['database_entry'];
            if (! $this->isSafeEntryName($entry)
                || ($isDatabase && $restorePath !== $entry)
                || (! $isDatabase && (! preg_match('/^storage\\/private\\/\\d{6}\\.bin$/', $entry)
                    || ! is_string($restorePath) || ! str_starts_with($restorePath, 'storage/private/')
                    || ! $this->isSafeEntryName($restorePath) || isset($restorePaths[$restorePath])))
                || ! is_int($metadata['bytes'] ?? null) || $metadata['bytes'] < 0
                || ! is_string($metadata['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $metadata['sha256'])) {
                throw new RuntimeException('O manifesto contém um caminho ou checksum inválido.');
            }
            $restorePaths[$restorePath] = true;
            $expectedNames[] = $entry;
        }

        $actualNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name)) {
                throw new RuntimeException('O arquivo de backup contém uma entrada inválida.');
            }
            $actualNames[] = $name;
        }

        sort($expectedNames);
        sort($actualNames);
        if ($expectedNames !== $actualNames) {
            throw new RuntimeException('O conteúdo do ZIP diverge do manifesto de integridade.');
        }
    }

    private function newPrivateDestination(string $destination): string
    {
        $destination = trim($destination);
        if ($destination === '') {
            throw new RuntimeException('Informe uma pasta nova e isolada para restaurar o backup.');
        }

        $absolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $destination)
            ? $destination
            : base_path($destination);
        $parent = realpath(dirname($absolute));
        if ($parent === false) {
            throw new RuntimeException('A pasta pai da restauração precisa existir.');
        }

        $target = rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($absolute);
        if (file_exists($target) || is_link($target) || $this->isInside(public_path(), $target)) {
            throw new RuntimeException('A restauração exige uma pasta ainda inexistente e fora da pasta pública.');
        }

        return $target;
    }

    private function privateDirectory(string $directory): string
    {
        $absolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $directory)
            ? $directory
            : base_path($directory);
        $parent = realpath(dirname($absolute));
        if ($parent === false) {
            throw new RuntimeException('A pasta pai do backup precisa existir.');
        }

        $target = rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($absolute);
        if (basename($absolute) === '.' || basename($absolute) === '..' || $this->isInside(public_path(), $target)) {
            throw new RuntimeException('O destino do backup deve ficar fora da pasta pública.');
        }

        File::ensureDirectoryExists($target, 0700);
        $target = realpath($target);
        if ($target === false || ! is_dir($target)) {
            throw new RuntimeException('A pasta privada do backup não pôde ser criada.');
        }
        if ($this->isInside(public_path(), $target)) {
            throw new RuntimeException('O destino do backup deve ficar fora da pasta pública.');
        }

        return $target;
    }

    private function assertHealthySqlite(string $path): void
    {
        $pdo = new PDO('sqlite:'.$path);
        $result = $pdo->query('PRAGMA quick_check')->fetchColumn();
        if ($result !== 'ok') {
            throw new RuntimeException('O banco SQLite restaurado não passou na verificação de integridade.');
        }
    }

    private function archivePassword(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY precisa estar configurada para proteger e abrir backups.');
        }

        return hash_hmac('sha256', 'mix7-backup-zip-v1', $key);
    }

    private function assertZipSupport(): void
    {
        if (! class_exists(ZipArchive::class) || ! defined(ZipArchive::class.'::EM_AES_256')) {
            throw new RuntimeException('A extensão PHP ZIP com criptografia AES-256 é necessária para criar ou restaurar backups.');
        }
    }

    private function sqlValue(PDO $pdo, mixed $value, string $columnType): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (preg_match('/(?:^|\s|\()(?:binary|varbinary|tinyblob|blob|mediumblob|longblob)(?:$|\s|\()/i', $columnType)) {
            return "UNHEX('".bin2hex((string) $value)."')";
        }

        $quoted = $pdo->quote((string) $value, PDO::PARAM_STR);
        if (! is_string($quoted)) {
            throw new RuntimeException('Não foi possível codificar um valor do banco para a cópia SQL.');
        }

        return $quoted;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function write(mixed $handle, string $contents): void
    {
        $written = fwrite($handle, $contents);
        if ($written !== strlen($contents)) {
            throw new RuntimeException('Não foi possível gravar o arquivo temporário do backup.');
        }
    }

    private function isSafeEntryName(string $entry): bool
    {
        return ! str_starts_with($entry, '/')
            && ! str_contains($entry, '\\')
            && ! preg_match('/(^|\/)\.\.?($|\/)/', $entry)
            && ! preg_match('/^[A-Za-z]:/', $entry);
    }

    private function isInside(string $directory, string $path): bool
    {
        $directory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (DIRECTORY_SEPARATOR === '\\') {
            $directory = mb_strtolower($directory);
            $path = mb_strtolower($path);
        }

        return $path === $directory || str_starts_with($path, $directory.DIRECTORY_SEPARATOR);
    }
}
