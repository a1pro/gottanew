<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('profiles')->where('notification_method', '!=', 'email')->update([
            'notification_method' => 'email',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally left irreversible because prior values are not recoverable.
    }
};
