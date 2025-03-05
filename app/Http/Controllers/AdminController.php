<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AdminController extends Controller
{
 public function approveUser($userId)
{
    // Look for user with the specific user ID format
    $user = User::where('user_id', $userId)->first();

    if ($user) {
        // Check if the authenticated user is an admin or super_admin
        $currentRole = auth()->user()->role;
        if (!in_array($currentRole, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Approve the user
        $user->approved = 1;
        $user->save();

        return response()->json(['message' => 'User approved successfully.'], 200);
    }

    return response()->json(['message' => 'User not found.'], 404);
}



public function declineUser($id)
{
    $user = User::find($id);

    if ($user) {
        // Check if the authenticated user is an admin or super_admin
        $currentRole = auth()->user()->role;
        if (!in_array($currentRole, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Decline the user by deleting their record
        $user->delete();

        return response()->json(['message' => 'User declined and deleted successfully'], 200);
    }

    return response()->json(['message' => 'User not found.'], 404);
}


    public function addUser(Request $request)
{
    // Validate the incoming request
    $validator = Validator::make($request->all(), [
        'firstname' => 'required|string|max:255',
        'lastname' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
        'password_confirmation' => 'required_with:password',
        'role' => 'required|in:user,admin,bhw',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Get the validated data
    $validated = $validator->validated();
    $validated['password'] = Hash::make($validated['password']); // Hash the password

    // Set is_admin to true if the role is 'admin', otherwise false
    $validated['is_admin'] = $validated['role'] === 'admin';

    // Create the user with the validated data
    $user = User::create($validated);

    return response()->json([
        'message' => 'User created successfully',
        'user' => $user,
    ], 201);
}

}
