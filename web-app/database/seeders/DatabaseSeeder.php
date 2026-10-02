<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $connectionConfig = config('database.connections.'.$connection, []);
        $configuredDatabase = $connectionConfig['database'] ?? null;

        if (app()->environment('local') && ($connectionConfig['driver'] ?? null) === 'sqlite'
            && is_string($configuredDatabase) && basename(str_replace('\\', '/', $configuredDatabase)) === 'mix7-demo.sqlite') {
            $this->call(DemoWorkspaceSeeder::class);
        }
    }
}
