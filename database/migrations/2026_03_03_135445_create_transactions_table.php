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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('coaching_packages')->nullOnDelete();

            $table->enum('transaction_type',['coin_purchase','coach_payment','refund']);

            $table->string('amount_currency')->nullable();
            $table->decimal('amount_fiat',10,2)->nullable();

            $table->integer('coin_amount');

            $table->string('stripe_session_id')->nullable();

            $table->enum('status',['pending','completed','failed','refunded'])
                ->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
