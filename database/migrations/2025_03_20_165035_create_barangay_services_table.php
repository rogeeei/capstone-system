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
         Schema::create('barangay_services', function (Blueprint $table) {
            $table->id();
            $table->string('brgy'); 
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade'); // Links to predefined services
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangay_services');
    }
};
