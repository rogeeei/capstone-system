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
        Schema::create('citizen_history_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('citizen_history_id'); // Foreign key to citizen_history table
            $table->unsignedBigInteger('service_id'); // Foreign key to services table
            $table->timestamps();

            // Foreign key constraints with consistent naming
            $table->foreign('citizen_history_id')
                ->references('citizen_history_id') // Correct reference to citizen_history table
                ->on('citizen_history')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id') // Correct reference to services table
                ->on('services')
                ->onDelete('cascade');

            // Add indexes to the foreign keys for better performance
            $table->index('citizen_history_id');
            $table->index('service_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_history_service');
    }
};
