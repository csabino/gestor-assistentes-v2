<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela temporária de diagnóstico: os logs do Laravel (storage/logs/laravel.log)
 * nunca aparecem nesse container de produção (nem em arquivo, nem na aba "Logs" do
 * EasyPanel, que só mostra o access log do servidor web). Enquanto isso não é
 * resolvido, gravamos aqui as respostas cruas da UazAPI para poder consultar via
 * tinker. Remover assim que o problema de conexão do WhatsApp for resolvido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_debug_log', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->integer('http_status')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_debug_log');
    }
};
