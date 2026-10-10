<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Ingrid_20261010_10h47"
            $table->string('target_label'); // texto livre, NÃO FK - só rotula qual agente foi escolhido no combo
            $table->string('target_phone_number');
            $table->foreignId('harness_assistant_id')->constrained('assistants')->cascadeOnDelete();

            // Retrato do prompt/base no momento em que o teste começou - nunca relido do assistente
            // de origem depois disso, pra manter o módulo desacoplado (editar o assistente real
            // depois não deve afetar um teste já rodado ou em andamento).
            $table->longText('target_prompt_snapshot');
            $table->longText('target_knowledge_snapshot')->nullable();

            $table->string('status')->default('draft'); // draft|running|completed|stopped|error
            $table->unsignedSmallInteger('current_scenario_index')->default(0);
            $table->unsignedSmallInteger('current_scenario_turn')->default(0);

            $table->json('scenarios')->nullable(); // plano gerado: [{title, category, persona_description, objective}, ...]
            $table->json('transcript')->nullable(); // [{scenario_index, role: 'tester'|'target', content, at}, ...]
            $table->json('report')->nullable(); // achados estruturados: {findings: [...], summary}

            $table->timestamp('last_message_sent_at')->nullable(); // usado pelo watchdog pra detectar teste travado
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_runs');
    }
};
