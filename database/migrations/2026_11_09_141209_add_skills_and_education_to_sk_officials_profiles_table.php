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
        Schema::table('sk_officials_profiles', function (Blueprint $table) {
            $table->string('educational_attainment', 100)->nullable()->after('purok_sitio');
            $table->string('education_degree', 150)->nullable()->after('educational_attainment');
            $table->string('education_school', 200)->nullable()->after('education_degree');
            $table->json('skills')->nullable()->after('education_school');
            $table->string('civil_status', 50)->nullable()->after('skills');
            $table->string('blood_type', 10)->nullable()->after('civil_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sk_officials_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'educational_attainment',
                'education_degree',
                'education_school',
                'skills',
                'civil_status',
                'blood_type',
            ]);
        });
    }
};
