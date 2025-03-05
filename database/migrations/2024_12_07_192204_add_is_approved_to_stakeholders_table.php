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
    $table->boolean('is_approved')->default(false); // Default to false (not approved)
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
