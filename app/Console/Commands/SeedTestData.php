<?php

namespace App\Console\Commands;

use App\Models\Assistant;
use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedTestData extends Command
{
    protected $signature = 'app:seed-test-data
        {--assistants=18 : Quantidade de assistentes fictícios a criar}
        {--departments=5 : Quantidade de departamentos fictícios a criar}
        {--users=5 : Quantidade de usuários fictícios a criar}
        {--cleanup : Remove todos os registros fictícios criados por este comando, em vez de criar}';

    protected $description = 'Cria (ou remove) assistentes, departamentos e usuários fictícios só para testar a interface (rolagem, listas grandes etc.)';

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $assistantsCount = (int) $this->option('assistants');
        $departmentsCount = (int) $this->option('departments');
        $usersCount = (int) $this->option('users');

        if (!$this->confirm("Isso vai criar {$assistantsCount} assistentes, {$departmentsCount} departamentos e {$usersCount} usuários FICTÍCIOS (prefixo \"TESTE\") só para testar a tela. Continuar?")) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $assistants = collect();
        for ($i = 1; $i <= $assistantsCount; $i++) {
            $assistants->push(Assistant::firstOrCreate(
                ['name' => sprintf('TESTE - Assistente %02d', $i)],
                ['is_active' => true]
            ));
        }
        $this->info("{$assistantsCount} assistentes fictícios prontos.");

        $departments = collect();
        for ($i = 1; $i <= $departmentsCount; $i++) {
            if ($assistants->isEmpty()) {
                $this->warn('Nenhum assistente disponível para vincular departamentos.');
                break;
            }

            $assistant = $assistants[$i % $assistants->count()];

            $departments->push(Department::firstOrCreate([
                'assistant_id' => $assistant->id,
                'name' => sprintf('TESTE - Departamento %02d', $i),
            ]));
        }
        $this->info("{$departmentsCount} departamentos fictícios prontos.");

        for ($i = 1; $i <= $usersCount; $i++) {
            $email = sprintf('teste.usuario%02d@teste.local', $i);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => sprintf('TESTE - Usuário %02d', $i),
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'agente',
                    'is_active' => true,
                ]
            );

            if ($departments->isNotEmpty()) {
                $dept = $departments[$i % $departments->count()];
                $user->departments()->syncWithoutDetaching([$dept->id]);
            }

            $this->info("Usuário fictício pronto: {$email}");
        }

        $this->info('Concluído. Todos os registros de teste usam o prefixo "TESTE" no nome (e-mails @teste.local) para facilitar localizar.');
        $this->info('Para remover tudo depois: php artisan app:seed-test-data --cleanup');

        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        if (!$this->confirm('Isso vai excluir TODOS os registros fictícios criados por este comando (assistentes, departamentos e usuários com prefixo "TESTE"). Continuar?')) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $users = User::where('email', 'like', 'teste.usuario%@teste.local')->get();
        foreach ($users as $user) {
            $user->departments()->detach();
            $user->delete();
        }
        $this->info("{$users->count()} usuários fictícios removidos.");

        $departments = Department::where('name', 'like', 'TESTE - Departamento%')->get();
        foreach ($departments as $dept) {
            $dept->delete();
        }
        $this->info("{$departments->count()} departamentos fictícios removidos.");

        $assistantsCount = Assistant::where('name', 'like', 'TESTE - Assistente%')->delete();
        $this->info("{$assistantsCount} assistentes fictícios removidos.");

        return self::SUCCESS;
    }
}
