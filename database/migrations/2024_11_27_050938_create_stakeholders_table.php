<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('stakeholders', function (Blueprint $table) {
        $table->id();
        $table->text('barangay');
        $table->text('municipality');
        $table->text('province');
        $table->boolean('is_approved')->default(false);
        $table->string('username'); 
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stakeholders');
    }
};
