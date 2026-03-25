<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_outbox')) {
            return;
        }

        Schema::create('message_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('dedup_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('coaching_sessions')->nullOnDelete();
            $table->string('provider', 30)->default('twilio');
            $table->enum('channel', ['sms', 'whatsapp']);
            $table->string('recipient_phone', 40);
            $table->string('sender_id', 100)->nullable();
            $table->text('body');
            $table->json('payload')->nullable();
            $table->string('provider_message_id', 64)->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->enum('status', ['pending', 'sending', 'sent', 'delivered', 'read', 'failed', 'undelivered', 'cancelled'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['provider_message_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_outbox');
    }
};
