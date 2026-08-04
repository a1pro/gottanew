<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. 'pre_session_summary', 'post_session_summary'
            $table->string('label');
            $table->text('description')->nullable();
            $table->longText('system_prompt');
            $table->unsignedInteger('max_tokens')->default(1500);
            $table->decimal('temperature', 3, 2)->default(0.40);
            $table->string('model')->nullable(); // null = use default model from config/services.php
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};