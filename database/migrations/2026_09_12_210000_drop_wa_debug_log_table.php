<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a tabela temporária de diagnóstico (wa_debug_log): o problema de conexão
 * do WhatsApp que ela ajudou a diagnosticar (token desatualizado) já foi resolvido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wa_debug_log');
    }

    public function down(): void
    {
        // Não recriamos a tabela de debug no rollback; se precisar dela de novo,
        // veja a migration 2026_09_12_200000_create_wa_debug_log_table.php.
    }
};
