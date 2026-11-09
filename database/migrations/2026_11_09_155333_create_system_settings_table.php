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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->longText('value')->nullable();
            $table->string('type', 30)->default('string'); // string, boolean, integer, json
            $table->string('group', 50)->default('general'); // general, registration, security, governance
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });

        // Auto-seed default system configurations upon creation
        if (class_exists(\Database\Seeders\SystemSettingsSeeder::class)) {
            (new \Database\Seeders\SystemSettingsSeeder())->run();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
