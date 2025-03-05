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
        Schema::create('citizen_service', function (Blueprint $table) {
            $table->id();
            $table->string('citizen_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();

            // Define foreign keys
            $table->foreign('citizen_id')->references('citizen_id')->on('citizen_details')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade'); // Corrected column name for services table

            // Add indexes for the pivot columns
            $table->index('citizen_id');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_service');
    }
};
