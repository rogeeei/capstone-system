<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
     public function index()
    {
         // Assuming 'role_id' is the foreign key and 2 represents the admin role
    $users = User::where('role', '!=', 'super_admin')->get();
    return response()->json($users);
    }
 public function getAdmin()
{
    $getAdmin = User::where('role', 'admin')->get();

    return response()->json($getAdmin->map(function ($user) {
        $user->role = ucfirst($user->role); // Capitalize first letter
        return $user;
        
    }));
}
// In SuperAdminController.php

public function getApprovedAdmin()
{
    $getAdmin = User::where('role', 'admin')
                    ->where('approved', true)
                    ->get();

    return response()->json($getAdmin->map(function ($user) {
        $user->role = ucfirst($user->role); // Capitalize first letter
        return $user;
    }));
}

public function getUsers()
{
    $getUsers = User::where('role', 'user')->get();

    return response()->json($getUsers->map(function ($user) {
        $user->role = ucfirst($user->role); // Capitalize first letter
        return $user;
    }));
}
public function getApprovedUsers()
{
    $getUsers = User::where('role', 'user')
                    ->where('approved', 1) // Only get approved users
                    ->get();

    return response()->json($getUsers->map(function ($user) {
        $user->role = ucfirst($user->role); // Capitalize first letter
        return $user;
    }));
}

}
