<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_locks', function (Blueprint $table) {


            $table->id();

            $table->foreignId('session_id')
                ->constrained('coaching_sessions')
                ->cascadeOnDelete();

            $table->string('locked_by');

            $table->timestamp('locked_at')->useCurrent();

            // FIX HERE
            $table->timestamp('expires_at')->nullable();

            $table->string('operation_type');
            $table->json('metadata')->nullable();

            $table->timestamps(); // optional but recommended
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_locks');
    }
};