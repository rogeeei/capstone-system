<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Controllers\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
public function getBhw()
{
    $adminBarangay = auth()->user()->brgy; // ✅ Get the authenticated admin's barangay

    // Retrieve users with the 'user' role who belong to the same barangay as the admin
    $bhwUsers = User::where('role', 'user')
                    ->where('brgy', $adminBarangay) // ✅ Filter by barangay
                    ->get();

    // Capitalize the first letter of the role for each user
    $bhwUsers->transform(function ($user) {
        $user->role = ucfirst($user->role);
        return $user;
    });

    // Return the filtered users as a JSON response
    return response()->json($bhwUsers);
}



public function getUsersWithMedicinesByBrgy()
{
    // Check if authenticated user exists
    $user = auth()->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Retrieve the authenticated user's barangay
    $userBarangay = $user->brgy;
    if (!$userBarangay) {
        return response()->json(['message' => 'User has no barangay assigned'], 400);
    }

    // Fetch users with medicines in the same barangay
    $usersWithMedicines = User::where('brgy', $userBarangay)
        ->whereHas('medicines') // Ensures the user has medicines
        ->with(['medicines' => function ($query) {
            $query->select('medicine_id', 'user_id', 'name', 'quantity');
        }])
        ->select('id', 'name', 'brgy')
        ->get();

    if ($usersWithMedicines->isEmpty()) {
        return response()->json(['message' => 'No users with medicines found'], 200);
    }

    return response()->json($usersWithMedicines);
}


    /**
     * Store a newly created resource in storage.
     */
   public function store(UserRequest $request)
{
    $validated = $request->validated();
    $validated['password'] = Hash::make($validated['password']);

    // Get the last user_id that starts with '211-'
    $lastUser = User::where('user_id', 'like', '211-%')
                    ->orderBy('user_id', 'desc')
                    ->first();

    // Extract the incrementing number, remove the '211-' prefix
    $lastNumber = $lastUser ? (int) substr($lastUser->user_id, 4) : 0;

    // Increment the number and format it with leading zeros
    $newUserId = '211-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

    // Assign the generated user_id
    $validated['user_id'] = $newUserId;

    // Create the user with the generated user_id
    $user = User::create($validated);

    return response()->json([
        'message' => 'User created successfully',
        'user' => $user
    ], 201);
}



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return User::findOrFail($id);
    }


    /**
     * Update the password of the specified resource in storage.
     */
    public function password(UserRequest $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validated();

        $user->password = Hash::make($validated['password']);

        $user->save();

        return $user;
    }


    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, string $id)
{
    // Find the user by ID or fail with a 404
    $user = User::findOrFail($id);

    // Validate the incoming data
    $validated = $request->validate([
        'firstname' => 'nullable|string|max:255',
        'middle_name' => 'nullable|string|max:255',
        'lastname' => 'nullable|string|max:255',
        'suffix' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255|unique:users,email,' . $id . ',user_id', // Ignore current user
        'phone_number' => 'nullable|string|max:20|unique:users,phone_number,' . $id . ',user_id', // Ignore current user
        'birthdate' => 'nullable|date',
        'brgy' => 'nullable|string|max:255',
        'role' => 'nullable|string|max:255',
        'image_path' => 'nullable|string|max:255',
        'password' => 'nullable|string|min:8|confirmed', // Password confirmation required
    ]);

    // Update the user's data with validated values
    $user->update([
        'firstname' => $validated['firstname'] ?? $user->firstname,
        'middle_name' => $validated['middle_name'] ?? $user->middle_name,
        'lastname' => $validated['lastname'] ?? $user->lastname,
        'suffix' => $validated['suffix'] ?? $user->suffix,
        'email' => $validated['email'] ?? $user->email,
        'phone_number' => $validated['phone_number'] ?? $user->phone_number,
        'birthdate' => $validated['birthdate'] ?? $user->birthdate,
        'brgy' => $validated['brgy'] ?? $user->brgy,
        'role' => $validated['role'] ?? $user->role,
        'image_path' => $validated['image_path'] ?? $user->image_path,
        'password' => isset($validated['password']) ? bcrypt($validated['password']) : $user->password,
    ]);

    // Return the updated user data as a JSON response
  return response()->json([
  'success' => true,
  'user' => $user->only(['firstname', 'middle_name', 'lastname', 'email', 'phone_number', 'birthdate', 'brgy', 'role', 'image_path']),
], 200);

}


    public function getUserDetails()
    {
        // Check if user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Return the necessary user details
        return response()->json([
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'barangay' => $user->brgy,  // Renamed to match the frontend's expected key
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return $user;
    }
}
