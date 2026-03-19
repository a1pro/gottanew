<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->longText('pre_session_summary')->nullable()->after('ai_summary');
            $table->longText('post_session_summary')->nullable()->after('pre_session_summary');
            $table->json('next_actions')->nullable()->after('post_session_summary');
            $table->timestamp('pre_session_generated_at')->nullable()->after('next_actions');
            $table->timestamp('post_session_generated_at')->nullable()->after('pre_session_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->dropColumn([
                'pre_session_summary',
                'post_session_summary',
                'next_actions',
                'pre_session_generated_at',
                'post_session_generated_at',
            ]);
        });
    }
};