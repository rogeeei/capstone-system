<?php

namespace App\Http\Controllers;

use App\Models\CitizenDetails;
use App\Http\Requests\CitizenDetailsRequest;
use App\Models\Medicine;
use App\Models\User;
use App\Models\CitizenHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class CitizenDetailsController extends Controller
{
public function index(Request $request)
{
    $query = CitizenDetails::query();

    // Check if a search is provided
    if ($request->has('search')) {
        $search = $request->input('search');
        $query->where(function ($query) use ($search) {
            $query->where('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
        });
    }

    // Fetch citizens with selected columns
    $citizens = $query->select('citizen_id', 'firstname', 'lastname', 'gender', 'address')->get();

    // Capitalize the gender field and return the result
    $citizens->map(function ($citizen) {
        $citizen->gender = ucfirst(strtolower($citizen->gender)); // Capitalize gender
        return $citizen;
    });

    return response()->json($citizens);
}


 public function getCitizenVisitHistory()
{
    $userBarangay = auth()->user()->brgy; // Get the authenticated user's barangay

    $history = DB::table('citizen_details')
        ->select(
            'citizen_details.created_at',
            'citizen_details.lastname',
            'citizen_details.firstname',
            'citizen_details.citizen_id',
            'citizen_details.gender',
            'citizen_details.date_of_birth',
            'citizen_details.purok',
            'citizen_details.barangay',
            'citizen_details.municipality',
            'citizen_details.province'
        )
        ->where('citizen_details.barangay', $userBarangay) // ✅ Filter by user's barangay
        ->orderBy('citizen_details.created_at', 'desc')
        ->distinct('citizen_details.citizen_id')
        ->take(3)
        ->get();

    if ($history->isEmpty()) {
        return response()->json(['message' => 'No histories found'], 404);
    }

    foreach ($history as $citizen) {
        $citizen->services_availed = CitizenDetails::find($citizen->citizen_id)->services;
    }

    return response()->json($history);
}


    /**
     * Show the service summary.
     *
     * @return \Illuminate\View\View
     */
    public function showServicesSummary()
    {
        try {
            // Fetch citizen data, using 'services_availed' as the membership date
            $citizens = CitizenDetails::select('citizen_id', 'firstname', 'middle_name', 'lastname', 'suffix', 'created_at')
                ->get();

            // Return the view with the citizens' data
            return response()->json($citizens);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching citizen details: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while fetching citizen details.'], 500);
        }
    }

    public function fetchServicesView(Request $request)
    {
        $citizens = CitizenDetails::with('services')->get();

        return response()->json($citizens);
    }



public function show(string $citizenId)
{
    // Retrieve the citizen with services, histories, and medicines
    $citizen = CitizenDetails::with(['services', 'histories.services', 'medicines'])
        ->where('citizen_id', $citizenId)
        ->first();

    // Check if the citizen was found
    if (!$citizen) {
        return response()->json(['message' => 'Citizen not found'], 404);
    }

    // Capitalize the first letter of the gender
    $citizen->gender = ucfirst(strtolower($citizen->gender));

    // Prepare the citizen details including all relevant fields
    $citizenData = [
        'citizen_id' => $citizen->citizen_id,
        'firstname' => $citizen->firstname,
        'middle_name' => $citizen->middle_name,
        'lastname' => $citizen->lastname,
        'suffix' => $citizen->suffix,
        'purok' => $citizen->purok,
        'barangay' => $citizen->barangay,
        'municipality' => $citizen->municipality,
        'province' => $citizen->province,
        'date_of_birth' => $citizen->date_of_birth,
        'gender' => $citizen->gender,
        'blood_type' => $citizen->blood_type,
        'height' => $citizen->height,
        'weight' => $citizen->weight,
        'allergies' => $citizen->allergies,
        'medication' => $citizen->medication,
        'emergency_contact_name' => $citizen->emergency_contact_name,
        'emergency_contact_no' => $citizen->emergency_contact_no,
    ];

    // Return the JSON response with all citizen details, services, and medicines availed
    return response()->json($citizenData);
}


public function getTransaction(string $citizenId)
{
    // Retrieve citizen details along with their services through the CitizenService pivot table and histories
    $citizen = CitizenDetails::with(['services', 'histories.services'])
        ->where('citizen_id', $citizenId)
        ->first();

    // Check if the citizen was found
    if (!$citizen) {
        return response()->json(['message' => 'Citizen not found'], 404);
    }

    // Initialize an empty collection for all services availed by the citizen
    $allServices = collect();

    // Loop through the citizen's services (accessed via the pivot table)
    foreach ($citizen->services as $service) {
        $allServices->push([
            'service_id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'created_at' => $service->pivot->created_at, // Date from the pivot table (CitizenService)
        ]);
    }

    // Loop through the citizen's histories to get services availed within each history
    foreach ($citizen->histories as $history) {
        foreach ($history->services as $service) {
            $allServices->push([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'created_at' => $history->created_at, // Use history's created_at as the service date
            ]);
        }
    }

    // Sort all services by the created_at date
    $allServices = $allServices->sortBy('created_at');

    // Prepare the diagnosis history data
    $diagnosisHistory = $this->getDiagnosisHistory($citizen);

    // Return the response with services and diagnosis history
    return response()->json([
        'citizen_id' => $citizen->citizen_id,
    ]);
}

public function store(CitizenDetailsRequest $request)
{
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $validated = $request->validated();
    $validated['municipality'] = $user->municipality;
    $validated['province'] = $user->province;

    $bmi = $this->calculateBMI($validated['height'], $validated['weight']);
    $bmiCategory = $this->getBmiCategory($bmi);
    $validated['bmi_category'] = $bmiCategory;

    // Check for existing citizen
    $existingCitizen = CitizenDetails::where([
        ['firstname', $validated['firstname']],
        ['lastname', $validated['lastname']],
        ['middle_name', $validated['middle_name']],
        ['date_of_birth', $validated['date_of_birth']],
        ['purok', $validated['purok']],
        ['barangay', $validated['barangay']],
        ['municipality', $validated['municipality']],
        ['gender', $validated['gender']]
    ])->first();

    if ($existingCitizen) {
        // Store citizen history and return response
        $citizenHistory = $this->createCitizenHistory($existingCitizen);
        return response()->json([
            'citizen_history' => $citizenHistory,
            'isNew' => false,
        ], 200);
    }

    // Generate new Citizen ID and create citizen record
    $newCitizenId = $this->generateCitizenId();
    $validated['citizen_id'] = $newCitizenId;

    $citizen = CitizenDetails::create($validated);
    $citizenHistory = $this->createCitizenHistory($citizen);

    return response()->json([
        'citizen' => $citizen,
        'citizen_history' => $citizenHistory,
        'isNew' => !$citizen->exists,
    ], 201);
}

protected function calculateBMI($height, $weight)
{
    $heightInMeters = (float) $height / 100;
    $weightInKg = (float) $weight;
    return $weightInKg / ($heightInMeters * $heightInMeters);
}

protected function getBmiCategory($bmi)
{
    if ($bmi < 18.5) {
        return 'Underweight';
    } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
        return 'Normal weight';
    } elseif ($bmi >= 25 && $bmi <= 29.9) {
        return 'Overweight';
    }
    return 'Obese';
}

protected function createCitizenHistory($citizen)
{
    return CitizenHistory::create([
        'citizen_id' => $citizen->citizen_id,
        'firstname' => $citizen->firstname,
        'middle_name' => $citizen->middle_name,
        'lastname' => $citizen->lastname,
        'suffix' => $citizen->suffix,
        'purok' => $citizen->purok,
        'barangay' => $citizen->barangay,
        'municipality' => $citizen->municipality,
        'province' => $citizen->province,
        'date_of_birth' => $citizen->date_of_birth,
        'gender' => $citizen->gender,
        'blood_type' => $citizen->blood_type,
        'height' => $citizen->height,
        'weight' => $citizen->weight,
        'allergies' => $citizen->allergies,
        'medication' => $citizen->medication,
        'emergency_contact_name' => $citizen->emergency_contact_name,
        'emergency_contact_no' => $citizen->emergency_contact_no,
    ]);
}

protected function generateCitizenId()
{
    $lastCitizen = CitizenDetails::where('citizen_id', 'like', '200-%')
        ->orderBy('citizen_id', 'desc')
        ->first();

    $lastNumber = $lastCitizen ? (int) substr($lastCitizen->citizen_id, 4) : 0;
    return '200-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
}





public function getCitizens()
{
    // Fetch citizens with their services and medicines
    $citizens = CitizenDetails::with(['services', 'medicines'])->get();
    return response()->json($citizens);
}

public function getCitizensByBarangay(Request $request)
{
    // Get the authenticated user
    $user = auth()->user();
    
    // Get pagination and search query parameters
    $page = $request->get('page', 1);
    $perPage = $request->get('per_page', 15);
    $query = $request->get('query', '');

    // Check if the user is a super admin
    if ($user->role === 'super_admin') {
        // Super admin can view all citizens, apply search filter across multiple fields
        $citizens = CitizenDetails::with(['services', 'medicines'])
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('lastname', 'like', "%$query%")
                             ->orWhere('firstname', 'like', "%$query%")
                             ->orWhere('gender', 'like', "%$query%")
                             ->orWhere('barangay', 'like', "%$query%");
            })
            ->paginate($perPage, ['*'], 'page', $page);
    } else {
        // Other users can only view citizens from their barangay
        $barangay = $user->brgy ?? null;

        if (!$barangay) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an assigned barangay.'
            ], 403);
        }

        // Apply barangay filter and search query for non-super admin users
        $citizens = CitizenDetails::with(['services', 'medicines'])
            ->where('barangay', $barangay)
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('lastname', 'like', "%$query%")
                             ->orWhere('firstname', 'like', "%$query%")
                             ->orWhere('gender', 'like', "%$query%")
                             ->orWhere('barangay', 'like', "%$query%");
            })
            ->paginate($perPage, ['*'], 'page', $page);
    }

    // Format the response (capitalize gender)
    $citizens->getCollection()->transform(function ($citizen) {
        if ($citizen->gender) {
            $citizen->gender = ucfirst(strtolower($citizen->gender));
        }
        return $citizen;
    });

    // Handle case where no citizens are found
    if ($citizens->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No citizens found.',
        ], 404);
    }

    // Return the paginated citizens along with pagination info
    return response()->json([
        'success' => true,
        'message' => 'Citizens retrieved successfully.',
        'data' => $citizens->items(),  // Return the paginated items
        'totalPages' => $citizens->lastPage(), // Total pages for pagination
        'currentPage' => $citizens->currentPage(), // Current page number
    ]);
}


public function getAllUserBarangays()
{
    try {
        // ✅ Get the logged-in user
        $user = auth()->user();

        // ✅ Ensure the user has a province and municipality assigned
        if (!$user || !$user->province || !$user->municipality) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an assigned province and municipality.'
            ], 403);
        }

        // ✅ Fetch barangays that belong to the user's province and municipality
        $barangays = User::whereNotNull('brgy') // Ignore null barangays
            ->where('approved', true) // ✅ Only include approved users
            ->where('role', '!=', 'super_admin') // ✅ Exclude super admins
            ->where('province', $user->province) // ✅ Filter by user’s province
            ->where('municipality', $user->municipality) // ✅ Filter by user’s municipality
            ->orderBy('brgy', 'asc') // ✅ Sort alphabetically
            ->distinct()
            ->pluck('brgy');

        // ✅ If no barangays found, return a message
        if ($barangays->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No barangays found in your assigned province and municipality.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Barangays retrieved successfully for your assigned province and municipality.',
            'province' => $user->province,
            'municipality' => $user->municipality,
            'barangays' => $barangays
        ]);
    } catch (\Exception $e) {
        // ✅ Log error for debugging
        Log::error("Error fetching barangays for user: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving barangays.',
            'error' => $e->getMessage()
        ], 500);
    }
}





public function getDistinctUserBarangays()
{
    // ✅ Fetch distinct barangays from approved users, excluding super admins, sorted alphabetically
    $barangays = User::whereNotNull('brgy') // Ignore null barangays
        ->where('role', '!=', 'super_admin') // ✅ Exclude super admins
        ->where('approved', true) // ✅ Only include approved users
        ->orderBy('brgy', 'asc') // ✅ Sort alphabetically
        ->distinct()
        ->pluck('brgy');

    // ✅ If no barangays found, return a message
    if ($barangays->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No barangays found from approved users (excluding super admins).'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Distinct barangays retrieved successfully (from approved users, excluding super admins).',
        'barangays' => $barangays
    ]);
}


public function getDistinctBarangays(Request $request)
{
    // ✅ Get the authenticated user
    $user = auth()->user();

    // ✅ Check if the user is a super admin
    if ($user->role === 'super_admin') {
        // Super admin can view all distinct barangays
        $barangays = CitizenDetails::select('barangay')->distinct()->get();
    } else {
        // Other users can only view the barangays they belong to
        $barangay = $user->brgy ?? null;

        if (!$barangay) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an assigned barangay.'
            ], 403);
        }

        // Fetch the user's assigned barangay only (since others cannot view others' barangays)
        $barangays = CitizenDetails::where('barangay', $barangay)
            ->select('barangay')
            ->distinct()
            ->get();
    }

    // ✅ Handle case where no barangays are found
    if ($barangays->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No barangays found.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Barangays retrieved successfully.',
        'data' => $barangays
    ]);
}

public function getDistinctProvinces(Request $request)
{
    $user = auth()->user();

    if ($user->role === 'super_admin') {
        $provinces = User::where('role', '!=', 'super_admin')
            ->where('approved', true)
            ->whereNotNull('province')
            ->distinct('province')
            ->orderBy('province', 'asc')
            ->pluck('province');
    } else {
        $province = $user->province ?? null;

        if (!$province) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an assigned province.'
            ], 403);
        }

        $provinces = User::where('province', $province)
            ->where('approved', true)
            ->whereNotNull('province')
            ->distinct('province')
            ->pluck('province');
    }

    if ($provinces->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No provinces found.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Provinces retrieved successfully.',
        'data' => $provinces
    ]);
}



    /**
     * Update the specified citizen in the database.
     */
   public function update(Request $request, $id)
{
    // Validate the incoming request data
    $validated = $request->validate([
        'firstname' => 'nullable|string|max:255',
        'middle_name' => 'nullable|string|max:255',
        'lastname' => 'nullable|string|max:255',
        'suffix' => 'nullable|string|max:255',
        'purok' => 'nullable|string',
        'barangay' => 'nullable|string',
        'municipality' => 'nullable|string',
        'province' => 'nullable|string',
        'date_of_birth' => 'nullable|date|date_format:Y-m-d',
        'gender' => 'nullable|string|max:10',
        'blood_type' => 'nullable|string',
        'height' => 'nullable|string',
        'weight' => 'nullable|string',
        'allergies' => 'nullable|string|max:255',
        'condition' => 'nullable|string|max:255',
        'medication' => 'nullable|string|max:255',
        'emergency_contact_name' => 'nullable|string|max:255',
        'emergency_contact_no' => 'nullable|string|max:20',
    ]);

    // Find the citizen by ID
    $citizen = CitizenDetails::find($id);

    if (!$citizen) {
        return response()->json(['message' => 'Citizen not found'], 404);
    }

    // Update the citizen's details
    $citizen->update($validated);

    // Update the services associated with the citizen
    if ($request->has('services_availed')) {
        $citizen->services()->sync($request->input('services_availed'));
    }

    if (!empty($validated['medications_availed'])) {
    $medicationsWithQuantity = [];
    foreach ($validated['medications_availed'] as $medication) {
        // Ensure medicine_id and quantity are provided
        if (!isset($medication['medicine_id']) || !isset($medication['quantity'])) {
            continue;
        }

        $medicationsWithQuantity[$medication['medicine_id']] = [
            'quantity' => $medication['quantity']
        ];

        // Update medicine stock
        $medicine = Medicine::find($medication['medicine_id']);
        if ($medicine) {
            if ($medicine->quantity >= $medication['quantity']) {
                $medicine->quantity -= $medication['quantity'];
                $medicine->save();
            } else {
                return response()->json([
                    'message' => "Insufficient quantity for medicine ID {$medication['medicine_id']}"
                ], 400);
            }
        }
    }

    // Sync medicines to pivot table
    if (!empty($medicationsWithQuantity)) {
        $citizen->medicines()->sync($medicationsWithQuantity);
    }
}

    return response()->json([
    'message' => 'Citizen details updated successfully',
    'citizen' => $citizen,
    'diagnosis' => $citizen->diagnosis ?? 'N/A',
    'medications_availed' => $citizen->medicines->isEmpty()
        ? ['No medications availed']
        : $citizen->medicines->map(function ($medicine) {
            return [
                'name' => $medicine->name,
                'quantity' => $medicine->pivot->quantity
            ];
        })
]);

}
//Report JS
public function calculateAndGroupBMI()
{
    // ✅ Fetch all citizens who have height & weight data
    $citizens = CitizenDetails::whereNotNull('height')
        ->whereNotNull('weight')
        ->get();

    // ✅ Categories for BMI classification
    $bmiGroups = [
        'Underweight' => 0,
        'Normal Weight' => 0,
        'Overweight' => 0,
        'Obese' => 0
    ];

    $citizenBmiData = [];

    foreach ($citizens as $citizen) {
        // ✅ Convert height and weight to float (ensures calculations work)
        $height = floatval($citizen->height);
        $weight = floatval($citizen->weight);

        // ✅ Convert height from cm to meters
        if ($height > 0) {
            $heightInMeters = $height / 100;
        } else {
            continue; // Skip invalid heights (e.g., 0 or null)
        }

        // ✅ Calculate BMI
        $bmi = $weight / ($heightInMeters * $heightInMeters);
        $bmi = round($bmi, 2); // ✅ Round BMI to 2 decimal places

        // ✅ Determine BMI category
        if ($bmi < 18.5) {
            $category = 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
            $category = 'Normal Weight';
        } elseif ($bmi >= 25.0 && $bmi <= 29.9) {
            $category = 'Overweight';
        } else {
            $category = 'Obese';
        }

        // ✅ Add to category count
        $bmiGroups[$category]++;

        // ✅ Store citizen data for response
        $citizenBmiData[] = [
            'citizen_id' => $citizen->citizen_id,
            'name' => "{$citizen->firstname} {$citizen->lastname}",
            'height' => $height . " cm",
            'weight' => $weight . " kg",
            'bmi' => $bmi,
            'category' => $category,
        ];
    }

    return response()->json([
        'message' => 'BMI Calculation and Categorization Successful',
        'bmi_summary' => $bmiGroups,
        'citizens_bmi' => $citizenBmiData,
    ]);
}
//Admin Report
public function calculateAndGroupBMIByBarangay(Request $request)
{
    // ✅ Get barangay from the URL parameter
    $barangay = $request->query('barangay');

    if (!$barangay) {
        return response()->json(['error' => 'Barangay parameter is required'], 400);
    }

    // ✅ Fetch all citizens from the given barangay who have height & weight data
    $citizens = CitizenDetails::where('barangay', $barangay)
        ->whereNotNull('height')
        ->whereNotNull('weight')
        ->get();

    // ✅ Categories for BMI classification
    $bmiGroups = [
        'Underweight' => 0,
        'Normal Weight' => 0,
        'Overweight' => 0,
        'Obese' => 0
    ];

    $citizenBmiData = [];

    foreach ($citizens as $citizen) {
        // ✅ Convert height and weight to float (ensures calculations work)
        $height = floatval($citizen->height);
        $weight = floatval($citizen->weight);

        // ✅ Convert height from cm to meters
        if ($height > 0) {
            $heightInMeters = $height / 100;
        } else {
            continue; // Skip invalid heights (e.g., 0 or null)
        }

        // ✅ Calculate BMI
        $bmi = $weight / ($heightInMeters * $heightInMeters);
        $bmi = round($bmi, 2); // ✅ Round BMI to 2 decimal places

        // ✅ Determine BMI category
        if ($bmi < 18.5) {
            $category = 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
            $category = 'Normal Weight';
        } elseif ($bmi >= 25.0 && $bmi <= 29.9) {
            $category = 'Overweight';
        } else {
            $category = 'Obese';
        }

        // ✅ Add to category count
        $bmiGroups[$category]++;

        // ✅ Store citizen data for response
        $citizenBmiData[] = [
            'citizen_id' => $citizen->citizen_id,
            'name' => "{$citizen->firstname} {$citizen->lastname}",
            'height' => $height . " cm",
            'weight' => $weight . " kg",
            'bmi' => $bmi,
            'category' => $category,
        ];
    }

    return response()->json([
        'message' => "BMI Calculation for Barangay: $barangay",
        'barangay' => $barangay,
        'bmi_summary' => $bmiGroups,
        'citizens_bmi' => $citizenBmiData,
    ]);
}

public function calculateAndGroupBMIForUserBarangay()
{
    // ✅ Get the barangay of the logged-in user
    $userBarangay = auth()->user()->brgy; 

    if (!$userBarangay) {
        return response()->json(['error' => 'User barangay not found'], 400);
    }

    // ✅ Fetch all citizens from the user's barangay who have height & weight data
    $citizens = CitizenDetails::where('barangay', $userBarangay)
        ->whereNotNull('height')
        ->whereNotNull('weight')
        ->get();

    // ✅ Categories for BMI classification
    $bmiGroups = [
        'Underweight' => 0,
        'Normal Weight' => 0,
        'Overweight' => 0,
        'Obese' => 0
    ];

    $citizenBmiData = [];

    foreach ($citizens as $citizen) {
        // ✅ Convert height and weight to float
        $height = floatval($citizen->height);
        $weight = floatval($citizen->weight);

        // ✅ Convert height from cm to meters
        if ($height > 0) {
            $heightInMeters = $height / 100;
        } else {
            continue; // Skip invalid heights (e.g., 0 or null)
        }

        // ✅ Calculate BMI
        $bmi = $weight / ($heightInMeters * $heightInMeters);
        $bmi = round($bmi, 2);

        // ✅ Determine BMI category
        if ($bmi < 18.5) {
            $category = 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
            $category = 'Normal Weight';
        } elseif ($bmi >= 25.0 && $bmi <= 29.9) {
            $category = 'Overweight';
        } else {
            $category = 'Obese';
        }

        // ✅ Add to category count
        $bmiGroups[$category]++;

        // ✅ Store citizen data for response
        $citizenBmiData[] = [
            'citizen_id' => $citizen->citizen_id,
            'name' => "{$citizen->firstname} {$citizen->lastname}",
            'height' => $height . " cm",
            'weight' => $weight . " kg",
            'bmi' => $bmi,
            'category' => $category,
        ];
    }

    return response()->json([
        'message' => "BMI Calculation for User's Barangay: $userBarangay",
        'barangay' => $userBarangay,
        'bmi_summary' => $bmiGroups,
        'citizens_bmi' => $citizenBmiData,
    ]);
}





    public function destroy(string $id)
    {
        $citizenDetails = CitizenDetails::findOrFail($id);

        $citizenDetails->delete();

        return response()->json(null, 204);
    }

    public function getServicesSummary()
    {
        // Fetch the citizen data along with related service data (adjust the fields as necessary)
        $citizens = CitizenDetails::select('address', 'lastname', 'firstname', 'middle_name', 'suffix', 'created_at')
            ->get();

        // Return the data as JSON
        return response()->json($citizens);
    }


}
