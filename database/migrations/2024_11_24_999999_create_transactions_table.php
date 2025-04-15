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
       Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->string('citizen_id');  
    $table->text('blood_pressure')->nullable();
    $table->foreignId('service_id')->nullable()->constrained('services'); 
    $table->date('transaction_date');
    $table->timestamps();

    
    $table->foreign('citizen_id')->references('citizen_id')->on('citizen_details')->onDelete('cascade');
});

    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
