<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->unsignedTinyInteger('feedback_rating')->nullable()->after('privacy_settings');
            $table->text('feedback_notes')->nullable()->after('feedback_rating');
            $table->foreignId('feedback_submitted_by_user_id')->nullable()->after('feedback_notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->dropForeign(['feedback_submitted_by_user_id']);
            $table->dropColumn(['feedback_rating', 'feedback_notes', 'feedback_submitted_by_user_id']);
        });
    }
};
