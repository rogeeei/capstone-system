<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Controllers\Validator;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class UserController extends Controller
{
public function getBhw()
{
    $user = auth()->user();

    // ✅ Ensure user has location details
    if (!$user->brgy || !$user->municipality || !$user->province) {
        return response()->json([
            "success" => false,
            "message" => "User's barangay, municipality, or province is missing.",
            "user_details" => [
                "barangay" => $user->brgy,
                "municipality" => $user->municipality,
                "province" => $user->province
            ]
        ], 400);
    }

    // ✅ Fetch only unapproved users with role "user" in the same barangay, municipality, and province
    $unapprovedUsers = User::where('approved', false)
        ->where('role', 'user') // Ensure we get only users with role "user"
        ->where('brgy', $user->brgy)
        ->where('municipality', $user->municipality)
        ->where('province', $user->province)
        ->get();

    // 🛠 Check if users exist
    if ($unapprovedUsers->isEmpty()) {
        return response()->json([
            "success" => true,
            "message" => "No unapproved users found.",
            "user_details" => [
                "barangay" => $user->brgy,
                "municipality" => $user->municipality,
                "province" => $user->province
            ],
            "data" => []
        ]);
    }

    return response()->json([
        "success" => true,
        "message" => "Unapproved users retrieved successfully.",
        "user_details" => [
            "barangay" => $user->brgy,
            "municipality" => $user->municipality,
            "province" => $user->province
        ],
        "data" => $unapprovedUsers
    ]);
}





public function getPuroksByBarangay()
{
    $user = auth()->user();

    // ✅ Ensure user has complete location details
    if (!$user->brgy || !$user->municipality || !$user->province) {
        return response()->json([
            "success" => false,
            "message" => "User's address details are missing.",
            "user_details" => [
                "barangay" => $user->brgy,
                "municipality" => $user->municipality,
                "province" => $user->province
            ]
        ], 400);
    }

    // ✅ Fetch all unique puroks from the users table within the user's barangay, municipality, and province
    $puroks = User::where('brgy', $user->brgy)
        ->where('municipality', $user->municipality)
        ->where('province', $user->province)
        ->whereNotNull('purok') // Ensure purok is not NULL
        ->distinct() // Get unique puroks
        ->pluck('purok'); // Retrieve only the purok column

    // ✅ Handle case where no puroks are found
    if ($puroks->isEmpty()) {
        return response()->json([
            "success" => false,
            "message" => "No puroks found for the user's barangay.",
            "data" => []
        ], 404);
    }

    // ✅ Return the list of unique puroks
    return response()->json([
        "success" => true,
        "message" => "Puroks retrieved successfully.",
        "data" => $puroks
    ]);
}








public function getApprovedAdmins()
{
    $adminBarangay = auth()->user()->brgy; // ✅ Get the authenticated admin's barangay

    // Retrieve approved users with the 'admin' role who belong to the same barangay as the authenticated admin
    $approvedAdmins = User::where('role', 'admin')
                          ->where('brgy', $adminBarangay) // ✅ Filter by barangay
                          ->where('status', 'approved') // ✅ Only approved users
                          ->get();

    // Capitalize the first letter of the role for each user
    $approvedAdmins->transform(function ($user) {
        $user->role = ucfirst($user->role);
        return $user;
    });

    // Return the filtered users as a JSON response
    return response()->json($approvedAdmins);
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
public function store(UserRequest $request): JsonResponse 
{
    $validated = $request->validated();
    $validated['password'] = Hash::make($validated['password']);


    $lastUser = User::where('user_id', 'like', '211-%')
                    ->orderBy('user_id', 'desc')
                    ->first();

    $lastNumber = $lastUser ? (int) substr($lastUser->user_id, 4) : 0;

    
    $newUserId = '211-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

  
    $validated['user_id'] = $newUserId;

    
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

public function getMunicipalitiesByProvince(Request $request)
{
    try {
        // ✅ Get province from URL parameter
        $province = $request->query('province');

        // ✅ Validate if province is provided
        if (!$province) {
            return response()->json([
                "success" => false,
                "error" => "Province parameter is required."
            ], 400);
        }

        // ✅ Fetch distinct municipalities where users are approved
        $municipalities = User::where('province', $province)
            ->whereNotNull('municipality')
            ->where('approved', true) // ✅ Only include approved users
            ->distinct()
            ->pluck('municipality');

        // ✅ If no municipalities found, return an appropriate message
        if ($municipalities->isEmpty()) {
            return response()->json([
                "success" => false,
                "message" => "No municipalities found for the selected province from approved users."
            ], 404);
        }

        return response()->json([
            "success" => true,
            "message" => "Municipalities retrieved successfully from approved users.",
            "province" => $province,
            "municipalities" => $municipalities
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch municipalities.",
            "details" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ], 500);
    }
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

    public function getDistinctProvinces()
{
    try {
        // ✅ Fetch distinct provinces from approved users, excluding super admins
        $provinces = User::whereNotNull('province')
            ->whereNotIn('province', ['N/A', 'None', 'Not Available', ''])
            ->where('approved', true) // ✅ Only include approved users
            ->where('role', '!=', 'super_admin') // ✅ Exclude super admins
            ->orderBy('province', 'asc') // ✅ Sort alphabetically
            ->distinct()
            ->pluck('province');

        // ✅ If no provinces found, return a message
        if ($provinces->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid provinces found for approved users (excluding super admins).'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distinct provinces retrieved successfully (from approved users, excluding super admins).',
            'provinces' => $provinces
        ]);
    } catch (\Exception $e) {
        // ✅ Log error for debugging
        Log::error("Error fetching provinces: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while fetching provinces.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
