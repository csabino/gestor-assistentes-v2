<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('client_name')->nullable();
            // in_progress: conduzindo pergunta a pergunta. completed: todas respondidas.
            $table->string('status')->default('in_progress');
            $table->foreignId('current_question_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_response_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_response_answers');
        Schema::dropIfExists('survey_responses');
    }
};
