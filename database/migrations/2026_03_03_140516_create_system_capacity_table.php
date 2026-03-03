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
        Schema::create('system_capacity', function (Blueprint $table) {
            $table->id();

            $table->integer('active_sessions_count')->default(0);
            $table->integer('max_sessions_limit')->default(50);

            $table->integer('db_connections_used')->default(0);
            $table->integer('max_db_connections')->default(15);

            $table->timestamp('last_updated')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_capacity');
    }
};
