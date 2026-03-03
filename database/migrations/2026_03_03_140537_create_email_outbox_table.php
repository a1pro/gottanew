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
        Schema::create('email_outbox', function (Blueprint $table) {
        $table->id();

        $table->string('dedup_key')->unique();
        $table->string('template_name');

        $table->string('recipient_email');
        $table->string('recipient_name')->nullable();
        $table->string('subject');

        $table->json('payload');

        $table->enum('status',['pending','sending','sent','failed','cancelled'])
            ->default('pending');

        $table->integer('attempts')->default(0);
        $table->integer('max_attempts')->default(3);

        $table->text('last_error')->nullable();
        $table->timestamp('last_attempt_at')->nullable();
        $table->timestamp('sent_at')->nullable();

        $table->timestamp('scheduled_for')->nullable();
        $table->timestamp('expires_at')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_outbox');
    }
};
