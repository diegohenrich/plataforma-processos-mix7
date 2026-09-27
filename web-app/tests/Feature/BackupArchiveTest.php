<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupArchiveTest extends TestCase
{
    private string $workspace;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = storage_path('framework/testing/mix7-backup-'.Str::uuid());
        File::ensureDirectoryExists($this->workspace, 0700);
        $this->databasePath = $this->workspace.DIRECTORY_SEPARATOR.'source.sqlite';

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'filesystems.disks.local.root' => $this->workspace.DIRECTORY_SEPARATOR.'private',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_backup_is_encrypted_and_restores_database_and_private_files_to_a_new_isolated_folder(): void
    {
        DB::table('organizations')->insert([
            'name' => 'Mix7 backup test',
            'slug' => 'mix7-backup-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        File::ensureDirectoryExists(config('filesystems.disks.local.root').'/demand-attachments');
        File::put(config('filesystems.disks.local.root').'/demand-attachments/demo.txt', 'synthetic private attachment');

        $backupDirectory = $this->workspace.DIRECTORY_SEPARATOR.'backups';
        $this->artisan('mix7:backup:create', ['--directory' => $backupDirectory])
            ->expectsOutputToContain('Backup AES-256 criado fora da pasta pública')
            ->assertExitCode(0);

        $archives = File::files($backupDirectory);
        $this->assertCount(1, $archives);
        $archivePath = $archives[0]->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivePath) === true);
        $this->assertFalse($zip->getFromName('manifest.json'));
        $zipNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $zipNames[] = $zip->getNameIndex($index);
        }
        $this->assertContains('storage/private/000001.bin', $zipNames);
        $this->assertNotContains('storage/private/demand-attachments/demo.txt', $zipNames);
        $key = config('app.key');
        $password = hash_hmac('sha256', 'mix7-backup-zip-v1', $key);
        $zip->setPassword($password);
        $this->assertStringContainsString('mix7-backup', $zip->getFromName('manifest.json'));
        $zip->close();

        $destination = $this->workspace.DIRECTORY_SEPARATOR.'restored-copy';
        $this->artisan('mix7:backup:restore', [
            'archive' => $archivePath,
            '--destination' => $destination,
        ])->expectsOutputToContain('Cópia restaurada e validada em pasta isolada')
            ->assertExitCode(0);

        $restoredDatabase = new PDO('sqlite:'.$destination.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite');
        $this->assertSame('Mix7 backup test', $restoredDatabase->query('SELECT name FROM organizations')->fetchColumn());
        $this->assertSame(
            'synthetic private attachment',
            File::get($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'demand-attachments'.DIRECTORY_SEPARATOR.'demo.txt'),
        );
        $this->assertDatabaseHas('organizations', ['slug' => 'mix7-backup-test']);
    }

    public function test_restore_rejects_a_wrong_app_key_without_leaving_a_partial_destination(): void
    {
        $this->artisan('mix7:backup:create', ['--directory' => $this->workspace.DIRECTORY_SEPARATOR.'backups'])
            ->assertExitCode(0);
        $archivePath = File::files($this->workspace.DIRECTORY_SEPARATOR.'backups')[0]->getPathname();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $destination = $this->workspace.DIRECTORY_SEPARATOR.'wrong-key-restore';

        $this->artisan('mix7:backup:restore', [
            'archive' => $archivePath,
            '--destination' => $destination,
        ])->expectsOutputToContain('manifesto válido ou foi protegido por outra APP_KEY')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($destination);
    }

    public function test_restore_never_overwrites_an_existing_directory_or_writes_under_public(): void
    {
        $this->artisan('mix7:backup:create', ['--directory' => $this->workspace.DIRECTORY_SEPARATOR.'backups'])
            ->assertExitCode(0);
        $archivePath = File::files($this->workspace.DIRECTORY_SEPARATOR.'backups')[0]->getPathname();
        $existing = $this->workspace.DIRECTORY_SEPARATOR.'existing';
        File::ensureDirectoryExists($existing);
        File::put($existing.DIRECTORY_SEPARATOR.'keep.txt', 'keep');

        $this->artisan('mix7:backup:restore', ['archive' => $archivePath, '--destination' => $existing])
            ->expectsOutputToContain('pasta ainda inexistente')
            ->assertExitCode(1);
        $this->artisan('mix7:backup:restore', [
            'archive' => $archivePath,
            '--destination' => public_path('mix7-restore-test-'.Str::uuid()),
        ])->expectsOutputToContain('fora da pasta pública')
            ->assertExitCode(1);

        $this->assertSame('keep', File::get($existing.DIRECTORY_SEPARATOR.'keep.txt'));
    }

    public function test_backup_refuses_an_in_memory_sqlite_database(): void
    {
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        $this->artisan('mix7:backup:create', ['--directory' => $this->workspace.DIRECTORY_SEPARATOR.'backups'])
            ->expectsOutputToContain('bancos em memória não podem ser copiados')
            ->assertExitCode(1);
    }

    public function test_backup_does_not_create_a_rejected_public_destination(): void
    {
        $destination = public_path('mix7-backup-test-'.Str::uuid());

        $this->artisan('mix7:backup:create', ['--directory' => $destination])
            ->expectsOutputToContain('fora da pasta pública')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($destination);
    }
}
