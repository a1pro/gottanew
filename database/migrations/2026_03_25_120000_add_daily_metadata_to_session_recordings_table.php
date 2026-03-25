<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('privacy_settings');
            $table->string('daily_recording_id')->nullable()->after('provider_name');
            $table->string('daily_transcript_id')->nullable()->after('daily_recording_id');
            $table->string('daily_recording_instance_id')->nullable()->after('daily_transcript_id');
            $table->string('daily_transcript_instance_id')->nullable()->after('daily_recording_instance_id');
            $table->json('provider_metadata')->nullable()->after('daily_transcript_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_recordings', function (Blueprint $table) {
            $table->dropColumn([
                'provider_name',
                'daily_recording_id',
                'daily_transcript_id',
                'daily_recording_instance_id',
                'daily_transcript_instance_id',
                'provider_metadata',
            ]);
        });
    }
};
