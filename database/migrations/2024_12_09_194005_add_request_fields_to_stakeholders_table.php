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
        Schema::table('stakeholders', function (Blueprint $table) {
             Schema::table('stakeholders', function (Blueprint $table) {
        $table->string('request_name')->nullable();
        $table->text('request_description')->nullable();
    });
        });
    }


    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stakeholders', function (Blueprint $table) {
            //
        });
    }
};
