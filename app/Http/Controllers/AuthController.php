<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Stakeholder;


class AuthController extends Controller
{
    /**
     * Log in to the specified resource.
     */
public function login(Request $request)
{
    \Log::info('Login Attempt:', $request->all());

    $validated = $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('username', $validated['username'])->first();

    if (!$user) {
        \Log::error('Login failed: User not found.', ['username' => $validated['username']]);
        return response()->json(['message' => 'Invalid username or password'], 401);
    }

    if (!Hash::check($validated['password'], $user->password)) {
        \Log::error('Login failed: Incorrect password.', [
            'username' => $validated['username']
        ]);
        return response()->json(['message' => 'Invalid username or password'], 401);
    }

    if (($user->role === 'admin' || $user->role === 'user') && !$user->approved) {
        \Log::warning('Login failed: User not approved.', ['username' => $validated['username']]);
        return response()->json([
            'message' => 'Your account is pending approval.',
            'role' => $user->role
        ], 403);
    }

    \Log::info('User Logged In:', ['username' => $user->username, 'role' => $user->role]);

    $token = $user->createToken('User Token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'role' => $user->role,  // ✅ Returning the role
        'token' => $token,
        'username' => $user->username,
    ], 200);
}





    /**
     * Log out the authenticated user.
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout successful'], 200);
    }

    /**
     * Log in a stakeholder using username and password only.
     */
   public function stakeholderLogin(Request $request)
{
    // Validate the request
    $validated = $request->validate([
        'username' => 'required|string',  // Ensure username is provided
        'password' => 'required|string',  // Ensure password is provided
    ]);

    // Attempt to find the stakeholder by username
    $stakeholder = Stakeholder::where('username', $validated['username'])->first();

    // Check if the stakeholder exists and the password is correct
    if (!$stakeholder || !Hash::check($validated['password'], $stakeholder->password)) {
        return response()->json(['message' => 'Invalid username or password'], 401);
    }

    // Check if the account is approved
    if (!$stakeholder->is_approved) {
        return response()->json([
            'message' => 'Your account has not been approved by the admin. Please wait for approval.'
        ], 403);
    }

    // Generate the token for the authenticated stakeholder
    $token = $stakeholder->createToken('Stakeholder Token')->plainTextToken;

    // Return response with token and agency details
    return response()->json([
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'id' => $stakeholder->id,
            'agency_name' => $stakeholder->agency_name,
            'username' => $stakeholder->username,
        ]
    ], 200);
}
}