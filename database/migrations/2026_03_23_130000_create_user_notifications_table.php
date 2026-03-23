<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('coaching_sessions')->nullOnDelete();
            $table->foreignId('coach_payout_id')->nullable()->constrained('coach_payouts')->nullOnDelete();
            $table->string('category', 100)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->string('channel', 20)->default('email');
            $table->string('delivery_status', 20)->default('stored');
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
