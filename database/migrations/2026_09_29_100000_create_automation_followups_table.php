<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            // Alvo real de envio (pode ter sufixo @lid pra contatos internacionais/LID do WhatsApp
            // - ver normalizeWaTarget()/sendTarget no webhook()). phone_number fica só com os dígitos,
            // igual ao resto do sistema (chat_messages, agendamentos etc.), usado pra identificar a conversa.
            $table->string('send_target');
            $table->unsignedInteger('attempts_sent')->default(0);
            // Última vez que o cliente respondeu OU que uma tentativa de retomada foi enviada -
            // é a partir daqui que se conta o intervalo até a próxima ação.
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->unique(['assistant_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_followups');
    }
};
