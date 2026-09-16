<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a tabela temporária de diagnóstico (ai_tag_debug): o mecanismo de tags
 * [AGUARDANDO_CLIENTE]/[MENU_FINAL_*] que ela ajudava a depurar foi removido - a decisão de quando
 * mostrar o menu de continuação voltou a ser 100% controlada pelo prompt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_tag_debug');
    }

    public function down(): void
    {
        // Não recriamos a tabela de debug no rollback.
    }
};
