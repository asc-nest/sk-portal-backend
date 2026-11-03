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
            $table->unsignedInteger('passed_measures_count')->default(14)->after('purok_sitio');
            $table->decimal('session_attendance_rate', 5, 2)->default(98.60)->after('passed_measures_count');
            $table->boolean('skmt_compliant')->default(true)->after('session_attendance_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sk_officials_profiles', function (Blueprint $table) {
            $table->dropColumn(['passed_measures_count', 'session_attendance_rate', 'skmt_compliant']);
        });
    }
};

