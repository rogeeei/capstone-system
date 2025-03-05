<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
    {
        // Check if a super admin already exists
        if (User::where('role', 'super_admin')->exists()) {
            return; 
        }

        // Create the super admin user
        User::create([
            'user_id' => 'superadmin001', 
            'firstname' => 'Super',
            'middle_name' => 'User',
            'lastname' => 'Admin',
            'suffix' => '',
            'email' => 'superadmin@example.com',
            'phone_number' => '1234567890',
            'birthdate' => '2007-07-28',
            'brgy' => 'Cabadbaran',
            'role' => 'super_admin', 
            'password' => Hash::make('superadminpassword'), 
            'approved' => true,
        ]);
    }
}
