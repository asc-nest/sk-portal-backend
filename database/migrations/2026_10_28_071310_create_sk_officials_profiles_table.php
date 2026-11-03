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
        Schema::create('sk_officials_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Names & Identity
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('nickname', 50)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->default('other');
            $table->date('birthdate')->nullable();
            $table->string('contact_number', 25)->nullable();
            
            // Official SK Council Role
            $table->enum('position', ['chairperson', 'kagawad', 'secretary', 'treasurer'])->default('kagawad');
            $table->string('committee_assigned', 150)->nullable();
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->boolean('is_active_term')->default(true);
            
            // Advocacy & Public Presentation
            $table->text('bio_advocacy')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('official_id_number', 50)->unique()->nullable();
            
            // Location
            $table->string('barangay_name', 100)->default('Barangay Central');
            $table->string('purok_sitio', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('position');
            $table->index('committee_assigned');
            $table->index('is_active_term');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sk_officials_profiles');
    }
};
