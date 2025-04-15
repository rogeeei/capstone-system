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
        Schema::create('users', function (Blueprint $table) {
            $table->string('user_id')->primary(); 
             $table->string('username');
            $table->string('firstname');
            $table->string('middle_name')->nullable();
            $table->string('lastname');
            $table->string('suffix')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number')->unique();
            $table->date('birthdate')->nullable();
            $table->string('brgy');
            $table->string('purok');
            $table->string('municipality');
            $table->string('province');
            $table->enum('role', ['super_admin', 'admin', 'user']);
            $table->string('image_path')->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamp('email_verified_at')->nullable();
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
        Schema::dropIfExists('users');
    }
};
