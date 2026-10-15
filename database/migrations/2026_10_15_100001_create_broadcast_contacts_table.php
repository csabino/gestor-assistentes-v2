<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone_raw');
            $table->string('phone_normalized')->nullable();
            $table->boolean('is_valid')->default(false);
            $table->string('invalid_reason')->nullable();
            $table->string('send_status')->default('pending');
            $table->text('send_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'is_valid', 'send_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_contacts');
    }
};
