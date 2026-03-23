<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_cycle_id')->constrained('payout_cycles')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->unsignedInteger('completed_sessions_count')->default(0);
            $table->unsignedInteger('session_coin_amount')->default(0);
            $table->decimal('payout_share_percentage', 8, 4)->default(0);
            $table->decimal('payout_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payout_cycle_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payouts');
    }
};
