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
   /**
     * Log in to the specified resource.
     */
    public function login(Request $request)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'user_id' => 'required|string',
            'brgy' => 'required|string',
            'role' => 'required|string',
            'password' => 'required|string',
        ]);

        // Retrieve the validated data
        $data = $validated;

        // Handle super admin login
        if ($data['role'] === 'super_admin') {
            // You may hardcode the super admin credentials, or check for a specific super admin user ID.
            $user = User::where('role', 'super_admin')
                        ->where('user_id', $data['user_id'])
                        ->first();

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Invalid credentials for Super Admin'], 401);
            }
        } else {
            // Handle admin or user login
            $user = User::where('user_id', $data['user_id'])
                        ->where('brgy', $data['brgy'])
                        ->where('role', $data['role'])
                        ->first();

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            // Admin approval check
            if ($user->role === 'admin' && !$user->approved) {
                return response()->json(['message' => 'Your admin account is pending approval by the super admin.'], 403);
            }

            // User approval check (only applies to user role)
            if ($user->role === 'user' && !$user->approved) {
                return response()->json(['message' => 'Your account is pending approval by the admin.'], 403);
            }
        }

        // Generate the token for the authenticated user
        $token = $user->createToken('User Token')->plainTextToken;

        // Prepare the response
        $response = [
            'token' => $token,
            'data' => [
                'role' => $user->role,
                'user_id' => $user->user_id,
            ],
        ];

        // Return the successful login response
        return response()->json(['message' => 'Login successful', 'data' => $response], 200);
    }


    /**
     * Log out of the specified resource.
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        $response = [
            'message' => 'Logout successful',
        ];
        return response()->json($response, 200);
    }

   public function stakeholderLogin(Request $request)
{
    // Validate incoming request data
    $validated = $request->validate([
        'agency_name' => 'required|string',
        'purok' => 'required|string',
        'barangay' => 'required|string',
        'municipality' => 'required|string',
        'province' => 'required|string',
    ]);

    // Attempt to find the stakeholder account
    $stakeholder = Stakeholder::where([
        ['agency_name', $validated['agency_name']],
        ['purok', $validated['purok']],
        ['barangay', $validated['barangay']],
        ['municipality', $validated['municipality']],
        ['province', $validated['province']],
    ])->first();

    // Check if the account exists
    if (!$stakeholder) {
        return response()->json(['message' => 'Invalid credentials. Please check your information and try again.'], 401);
    }

    // Check if the account is approved by the admin
    if (!$stakeholder->is_approved) {
        return response()->json(['message' => 'Your account has not been approved by the admin. Please wait for approval.'], 403);
    }

    // Generate the token for the authenticated stakeholder
    $token = $stakeholder->createToken('Stakeholder Token')->plainTextToken;

    // Prepare the response to include agency_name
    $response = [
        'token' => $token,
        'data' => [
            'id' => $stakeholder->id,
            'agency_name' => $stakeholder->agency_name,  // Include agency_name
        ],
    ];

    // Return the successful login response
    return response()->json(['message' => 'Login successful', 'data' => $response], 200);
}

}
