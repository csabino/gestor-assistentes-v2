<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            // Recorrente: repete todo ano no mesmo dia/mês (ex: Natal, 25/12), independente do ano
            // cadastrado. Não recorrente: bloqueia só aquela data exata (ex: Carnaval de um ano
            // específico, que muda de data ano a ano).
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
