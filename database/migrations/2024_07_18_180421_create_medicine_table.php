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
        Schema::create('medicine', function (Blueprint $table) {
            $table->string('medicine_id')->primary();
            $table->text('name');
            $table->text('usage_description');
            $table->integer('quantity');
            $table->string('unit'); 
            $table->date('expiration_date');
            $table->text('medicine_status');
            $table->date('date_acquired')->nullable();
           $table->string('user_id'); 
            
            // Foreign key to the users table
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

          $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine');
    }
};
