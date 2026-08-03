<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {

            $table->text('qualifications')->nullable()->after('specialties');

            $table->json('expertise_areas')->nullable()->after('qualifications');

            $table->text('coaching_philosophy')->nullable()->after('expertise_areas');

            $table->text('interests')->nullable()->after('coaching_philosophy');

            $table->json('preferred_client_types')->nullable()->after('interests');

            $table->json('industries')->nullable()->after('preferred_client_types');

            $table->text('favorite_challenges')->nullable()->after('industries');

            $table->string('website')->nullable()->after('favorite_challenges');

            $table->json('languages')->nullable()->after('website');

            $table->text('community_involvement')->nullable()->after('languages');

        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {

            $table->dropColumn([
                'qualifications',
                'expertise_areas',
                'coaching_philosophy',
                'interests',
                'preferred_client_types',
                'industries',
                'favorite_challenges',
                'website',
                'languages',
                'community_involvement',
            ]);

        });
    }
};