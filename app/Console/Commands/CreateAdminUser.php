<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Cria um usuário com acesso ao painel administrativo';

    public function handle(): int
    {
        $name = $this->ask('Nome');
        $email = $this->ask('E-mail');
        $password = $this->secret('Senha (mínimo 8 caracteres)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Usuário {$email} criado com sucesso.");
        return self::SUCCESS;
    }
}
