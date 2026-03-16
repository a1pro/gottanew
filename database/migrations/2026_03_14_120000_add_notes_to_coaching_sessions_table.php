<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->longText('client_notes')->nullable()->after('price_currency');
            $table->longText('coach_notes')->nullable()->after('client_notes');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->dropColumn(['client_notes', 'coach_notes']);
        });
    }
};