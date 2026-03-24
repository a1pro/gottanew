<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('coaching_sessions', 'is_intro_session')) {
                $table->boolean('is_intro_session')->default(false)->after('price_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('coaching_sessions', 'is_intro_session')) {
                $table->dropColumn('is_intro_session');
            }
        });
    }
};
