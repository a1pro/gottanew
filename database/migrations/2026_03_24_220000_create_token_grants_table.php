<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('coin_amount');
            $table->string('reason', 50)->default('manual_adjustment');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['granted_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_grants');
    }
};
