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
        Schema::create('council_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('barangay_name', 100)->default('Barangay Central');
            $table->string('session_number', 50)->comment('e.g. RS-2024-001 or SS-2024-001');
            $table->string('session_title', 255)->comment('e.g. 1st Regular Session - Q1 Annual Youth Review');
            $table->enum('session_type', ['regular', 'special'])->default('regular');
            $table->date('session_date');
            $table->string('venue', 150)->nullable()->default('Barangay Session Hall');
            $table->string('presided_by', 150)->nullable()->comment('Presiding Officer name or designation');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Secretary or Admin user ID');
            $table->text('notes')->nullable()->comment('Key agenda items or session highlights');
            $table->timestamps();

            $table->index(['barangay_name', 'session_date']);
        });

        Schema::create('session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_session_id')
                ->constrained('council_sessions')
                ->onDelete('cascade');
            $table->foreignId('official_profile_id')
                ->constrained('sk_officials_profiles')
                ->onDelete('cascade');
            $table->enum('status', ['present', 'absent', 'excused', 'late'])->default('present');
            $table->string('remarks', 255)->nullable()->comment('Reason for absence or official business details');
            $table->timestamps();

            $table->unique(['council_session_id', 'official_profile_id'], 'unique_session_official_attendance');
            $table->index(['official_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_attendances');
        Schema::dropIfExists('council_sessions');
    }
};

