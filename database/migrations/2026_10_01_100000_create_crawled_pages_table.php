<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawled_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            // Caminho no menu do site até essa página, ex: ["Home","Produtos","Insoft Omni"] -
            // usado pra nomear/organizar o arquivo em disco e exibir na tela "Ver Base de Conhecimento".
            $table->json('menu_path');
            $table->string('file_path');
            $table->unsignedInteger('content_size');
            $table->timestamp('crawled_at');
            $table->timestamps();

            $table->unique(['assistant_id', 'file_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawled_pages');
    }
};
