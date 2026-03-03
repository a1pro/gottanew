<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('session_state_logs', function (Blueprint $table) {
        $table->id();

        $table->foreignId('session_id')
                ->constrained('coaching_sessions')
                ->cascadeOnDelete();
                
        $table->string('from_state')->nullable();
        $table->string('to_state');

        $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

        $table->text('change_reason')->nullable();
        $table->json('metadata')->nullable();

        $table->timestamp('created_at')->useCurrent();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_state_logs');
    }
};
