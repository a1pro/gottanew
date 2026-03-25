<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('coaching_sessions')->nullOnDelete();
            $table->foreignId('user_notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
            $table->string('reminder_key', 50);
            $table->string('category', 100)->default('session_reminder');
            $table->string('priority', 20)->default('normal');
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('send_at');
            $table->enum('status', ['pending', 'sending', 'sent', 'cancelled', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'session_id', 'reminder_key'], 'scheduled_notifications_unique_reminder');
            $table->index(['status', 'send_at']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
    }
};
