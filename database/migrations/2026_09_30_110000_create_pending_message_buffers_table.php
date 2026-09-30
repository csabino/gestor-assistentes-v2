<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_message_buffers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            // Texto acumulado das mensagens rápidas em sequência (separadas por \n), aguardando o
            // fim da janela de debounce pra virar um único turno de IA - ver processo em webhook().
            $table->text('buffered_text');
            // Token trocado a cada nova mensagem que chega pra esse número - a requisição cujo
            // token não bate mais depois do sleep() perdeu a corrida (chegou uma mensagem mais
            // nova durante a espera) e desiste silenciosamente, sem chamar a IA.
            $table->string('token');
            $table->timestamps();

            $table->unique(['assistant_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_message_buffers');
    }
};
