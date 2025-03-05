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
         Schema::create('citizen_details', function (Blueprint $table) {
            $table->string('citizen_id')->primary(); 
            $table->text('firstname');
            $table->text('middle_name')->nullable();
            $table->text('lastname');
            $table->text('suffix')->nullable();
            $table->text('purok');
            $table->text('barangay');
            $table->text('municipality');
            $table->text('province');
            $table->date('date_of_birth')->nullable();
            $table->text('gender');
            $table->text('blood_type')->nullable();
            $table->text('height');
            $table->text('weight');
            $table->text('allergies')->nullable();
            $table->text('medication')->nullable();
            $table->text('emergency_contact_name');
            $table->text('emergency_contact_no');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_details');
    }
};
