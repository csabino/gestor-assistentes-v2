<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('avatar_path');
            $table->foreignId('human_agent_id')->nullable()->after('role')
                ->constrained('human_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['human_agent_id']);
            $table->dropColumn(['role', 'human_agent_id']);
        });
    }
};
