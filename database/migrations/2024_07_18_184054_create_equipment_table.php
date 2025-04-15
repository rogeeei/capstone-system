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
        Schema::create('equipment', function (Blueprint $table) {
            $table->string('equipment_id')->primary();
            $table->text('name');
            $table->text('description');
            $table->integer('quantity');
            $table->text('condition');
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
        Schema::dropIfExists('equipment');
    }
};
