<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('human_agent_id')->constrained('users')->nullOnDelete();
        });

        // A partir de agora, novos agendamentos são criados só com user_id (sem depender de human_agents).
        DB::statement('ALTER TABLE appointments MODIFY human_agent_id BIGINT UNSIGNED NULL');

        // Migra os registros da antiga tabela human_agents para usuários de verdade (login),
        // reaproveitando login já existente quando o e-mail já é de um usuário do painel.
        foreach (DB::table('human_agents')->get() as $agent) {
            $email = strtolower(trim((string) $agent->email));

            $existing = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($existing) {
                $userId = $existing->id;
            } else {
                $tempPassword = Str::random(12);

                $userId = DB::table('users')->insertGetId([
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'password' => Hash::make($tempPassword),
                    'role' => 'agente',
                    'human_agent_id' => $agent->id,
                    'is_active' => $agent->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                fwrite(STDOUT, "  [migração equipe] usuário criado para '{$agent->name}' ({$agent->email}) — senha temporária: {$tempPassword}\n");
            }

            DB::table('department_user')->insertOrIgnore([
                'department_id' => $agent->department_id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('appointments')->where('human_agent_id', $agent->id)->update(['user_id' => $userId]);
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::dropIfExists('department_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
