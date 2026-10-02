<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAgencyOwner extends Command
{
    protected $signature = 'mix7:owner:create';

    protected $description = 'Cria a primeira conta de direção da plataforma Mix7';

    public function handle(): int
    {
        if (User::where('role', UserRole::AgencyOwner->value)->exists()) {
            $this->components->error('Já existe uma conta de direção.');
            return self::FAILURE;
        }

        $name = trim((string) $this->components->ask('Nome da pessoa responsável'));
        $email = Str::lower(trim((string) $this->components->ask('E-mail de acesso')));
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $this->components->error('Nome obrigatório; e-mail inválido ou já em uso.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Crie uma senha forte', false);
        if (mb_strlen($password) < 12) {
            $this->components->error('A senha precisa ter pelo menos 12 caracteres.');
            return self::FAILURE;
        }

        $organization = Organization::firstOrCreate(['slug' => 'mix7'], ['name' => 'Mix7']);
        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'organization_id' => $organization->id,
            'role' => UserRole::AgencyOwner,
            'is_active' => true,
        ]);

        $this->components->info('Conta criada. A senha não será exibida novamente.');
        return self::SUCCESS;
    }
}
