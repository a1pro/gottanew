<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_goals', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('source_session_id')->nullable()->index();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');

            $table->decimal('progress_percentage', 5, 2)->default(0);

            $table->enum('status', ['active','completed','paused'])
                ->default('active');

            $table->timestamp('target_date')->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('source_session_id')
                ->references('id')
                ->on('coaching_sessions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
    }
};