<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            // Marca um assistant row como "número de teste" do módulo Testador: reaproveita 100% do
            // fluxo de conexão WhatsApp/sendWhatsappMessage/callAiApi já existente, mas o webhook()
            // desvia pra um fluxo totalmente separado em vez do atendimento normal - este número
            // nunca deve rodar o assistente de IA de verdade.
            $table->boolean('is_test_harness')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn('is_test_harness');
        });
    }
};
