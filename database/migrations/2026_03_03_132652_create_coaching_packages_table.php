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
            Schema::create('coaching_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description');
            $table->integer('duration_minutes');

            $table->decimal('price_amount',10,2);
            $table->string('price_currency')->default('USD');
            $table->integer('coin_cost');

            $table->enum('package_type',['basic','premium','vip']);
            $table->json('features');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coaching_packages');
    }
};
