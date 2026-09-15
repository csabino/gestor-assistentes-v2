<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela temporária de diagnóstico: captura o texto bruto da IA (antes de remover as tags
 * [AGUARDANDO_CLIENTE]/[MENU_PRINCIPAL]/etc) pra confirmar se ela está emitindo a tag em momentos
 * errados. Remover depois que o comportamento do menu de continuação estiver confirmado correto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tag_debug', function (Blueprint $table) {
            $table->id();
            $table->string('user_message');
            $table->longText('raw_reply');
            $table->boolean('has_waiting_tag');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tag_debug');
    }
};
