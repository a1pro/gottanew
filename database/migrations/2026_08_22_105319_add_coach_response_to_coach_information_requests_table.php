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
        Schema::table('coach_information_requests', function (Blueprint $table) {
            $table->text('coach_response')->nullable()->after('message');
        });
    }
    
    public function down(): void
    {
        Schema::table('coach_information_requests', function (Blueprint $table) {
            $table->dropColumn('coach_response');
        });
    }
};
