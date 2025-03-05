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
        // Create the citizen_history table
        Schema::create('citizen_history', function (Blueprint $table) {
            $table->id('citizen_history_id'); // Primary key
            $table->string('firstname');
            $table->string('middle_name')->nullable();
            $table->string('lastname');
           $table->text('purok');
            $table->text('barangay');
            $table->text('municipality');
            $table->text('province');
            $table->date('date_of_birth')->nullable();
            $table->string('gender');
            $table->string('blood_type')->nullable();
            $table->string('height');
            $table->string('weight');
            $table->string('allergies')->nullable();
            $table->string('medication')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_no');
            $table->string('citizen_id'); // Foreign key to citizen_details table
            $table->date('date')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('citizen_id')
                  ->references('citizen_id') // Match the existing primary key in citizen_details table
                  ->on('citizen_details') 
                  ->onDelete('cascade');

            // Add index to improve query performance for searching by citizen_id
            $table->index('citizen_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_history');
    }
};
