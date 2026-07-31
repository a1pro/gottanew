<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_resources', function (Blueprint $table) {
            $table->foreignId('managed_resource_id')
                ->nullable()
                ->after('session_id')
                ->constrained('managed_resources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_resources', function (Blueprint $table) {
            $table->dropForeign(['managed_resource_id']);
            $table->dropColumn('managed_resource_id');
        });
    }
};