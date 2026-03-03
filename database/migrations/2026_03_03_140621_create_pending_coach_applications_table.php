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
       Schema::create('pending_coach_applications', function (Blueprint $table) {
        $table->id();

        $table->string('name');
        $table->string('email')->unique();
        $table->string('phone')->nullable();

        $table->text('experience');
        $table->json('specialties');
        $table->text('message');

        $table->enum('status',['pending','approved','rejected','invited'])
            ->default('pending');

        $table->foreignId('reviewed_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamp('reviewed_at')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_coach_applications');
    }
};
