<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->string('message_selector')->nullable();
            $table->text('message_preview')->nullable();
            $table->unsignedInteger('batch_size')->nullable();
            $table->unsignedInteger('raw_contacts_count')->default(0);
            $table->unsignedInteger('valid_contacts_count')->default(0);
            $table->unsignedInteger('invalid_contacts_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sanitized_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_campaigns');
    }
};
