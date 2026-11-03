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
        Schema::create('official_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_profile_id')
                ->constrained('sk_officials_profiles')
                ->onDelete('cascade');
            $table->string('resolution_number', 50)->nullable()->comment('e.g. RES-2024-001 or BGY-RES-24-05');
            $table->string('title', 255)->comment('Title of the passed measure or resolution');
            $table->string('category', 100)->default('Youth Empowerment')->comment('Education, Sports, DRRM, Health, etc.');
            $table->string('authorship_role', 50)->default('principal_author')->comment('principal_author, co_author, sponsor');
            $table->string('status', 50)->default('passed')->comment('passed, adopted, enacted, under_review');
            $table->date('date_approved')->nullable();
            $table->text('summary')->nullable()->comment('Brief summary or legislative objectives');
            $table->timestamps();

            $table->index(['official_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('official_measures');
    }
};

