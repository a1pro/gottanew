<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_outbox')) {
            return;
        }

        Schema::table('email_outbox', function (Blueprint $table) {
            if (!Schema::hasColumn('email_outbox', 'user_notification_id')) {
                $table->foreignId('user_notification_id')->nullable()->after('dedup_key')->constrained('user_notifications')->nullOnDelete();
                $table->index(['user_notification_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_outbox') || !Schema::hasColumn('email_outbox', 'user_notification_id')) {
            return;
        }

        Schema::table('email_outbox', function (Blueprint $table) {
            $table->dropForeign(['user_notification_id']);
            $table->dropIndex('email_outbox_user_notification_id_created_at_index');
            $table->dropColumn('user_notification_id');
        });
    }
};
