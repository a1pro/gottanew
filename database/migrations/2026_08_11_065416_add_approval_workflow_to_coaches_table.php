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
        Schema::table('coaches', function (Blueprint $table) {
            $table->enum('approval_status', [
                'pending',
                'needs_information',
                'approved',
                'rejected',
            ])->default('pending')->after('is_active');

            $table->text('admin_notes')->nullable()->after('approval_status');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('admin_notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);

            $table->dropColumn([
                'approval_status',
                'admin_notes',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
