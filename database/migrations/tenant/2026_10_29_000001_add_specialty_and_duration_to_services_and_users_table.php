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
        Schema::table('services', function (Blueprint $table): void {
            $table->string('specialty')->default('general');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('specialties')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['specialty', 'duration_minutes']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('specialties');
        });
    }
};
