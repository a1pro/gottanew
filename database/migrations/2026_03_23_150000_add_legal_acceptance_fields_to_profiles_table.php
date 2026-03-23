<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('legal_version', 32)->nullable()->after('email_verified');
            $table->timestamp('terms_accepted_at')->nullable()->after('legal_version');
            $table->timestamp('privacy_policy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('coaching_disclaimer_accepted_at')->nullable()->after('privacy_policy_accepted_at');
            $table->timestamp('coach_independence_acknowledged_at')->nullable()->after('coaching_disclaimer_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'legal_version',
                'terms_accepted_at',
                'privacy_policy_accepted_at',
                'coaching_disclaimer_accepted_at',
                'coach_independence_acknowledged_at',
            ]);
        });
    }
};
