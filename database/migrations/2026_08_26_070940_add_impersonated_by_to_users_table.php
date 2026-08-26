<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('impersonated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['impersonated_by']);
            $table->dropColumn('impersonated_by');
        });
    }
};