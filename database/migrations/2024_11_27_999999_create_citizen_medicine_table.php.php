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
        // Create the citizen_medicine table
        Schema::create('citizen_medicine', function (Blueprint $table) {
            $table->id();
            $table->string('citizen_id');
            $table->string('medicine_id');
            $table->integer('quantity')->default(1); 
            $table->string('unit'); 
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('citizen_id')->references('citizen_id')->on('citizen_details')->onDelete('cascade');
            $table->foreign('medicine_id')->references('medicine_id')->on('medicine')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_medicine');
    }
};
