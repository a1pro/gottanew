<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('month_key', 7)->unique();
            $table->decimal('gross_revenue_amount', 10, 2)->default(0);
            $table->decimal('coach_pool_percentage', 5, 2)->default(80);
            $table->decimal('coach_pool_amount', 10, 2)->default(0);
            $table->decimal('platform_revenue_amount', 10, 2)->default(0);
            $table->unsignedInteger('completed_purchase_count')->default(0);
            $table->unsignedInteger('purchased_coin_amount')->default(0);
            $table->unsignedInteger('total_completed_sessions')->default(0);
            $table->unsignedInteger('total_session_coins')->default(0);
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_cycles');
    }
};
