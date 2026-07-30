<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_resources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('resource_type')->default('link');
            $table->string('title');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['created_by', 'created_at']);
            $table->index(['created_by', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_resources');
    }
};