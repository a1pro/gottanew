<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('preferred_coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->foreignId('assigned_coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->foreignId('approved_session_id')->nullable()->constrained('coaching_sessions')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->string('goal_summary', 255)->nullable();
            $table->text('request_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('viewer_timezone', 100)->default('UTC');
            $table->timestamp('scheduled_time')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_requests');
    }
};
