<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_sessions', function (Blueprint $table) {


            $table->id(); // unsignedBigInteger PRIMARY

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('coach_id');

            $table->timestamp('scheduled_time');

            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
                'no_show'
            ])->default('scheduled');

            $table->integer('duration_minutes')->default(0);

            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('price_currency')->default('USD');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('client_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('coach_id')
                ->references('id')
                ->on('coaches')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_sessions');
    }
};