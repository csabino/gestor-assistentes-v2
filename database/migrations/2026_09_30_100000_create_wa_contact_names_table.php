<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contact_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            // Último nome conhecido pra esse número (pushName do WhatsApp, ou nome resolvido pelo
            // Omni quando disponível) - usado só pra exibição na tela de Conversas, não afeta em
            // nada a lógica de atendimento.
            $table->string('name');
            $table->timestamps();

            $table->unique(['assistant_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_contact_names');
    }
};
