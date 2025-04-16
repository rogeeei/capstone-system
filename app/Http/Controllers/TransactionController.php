<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\CitizenDetails;
use App\Models\Services;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;




class TransactionController extends Controller
{
    public function index()
    {
        return Transaction::all();
    }

public function store(Request $request)
{
    // ✅ Validate the request
    $validated = $request->validate([
        'citizen_id' => 'required|string',
        'blood_pressure' => 'nullable|string',
        'service_id' => 'nullable|exists:services,id',
        'medicines' => 'nullable|array',
        'medicines.*.medicine_id' => 'nullable|exists:medicine,medicine_id',
        'medicines.*.quantity' => 'nullable|integer',
        'medicines.*.unit' => 'nullable|string', // Ensure unit is validated from the request
    ]);

    DB::beginTransaction(); 

    try {
        // ✅ Debug: Log request data
        \Log::info('Validated Request Data:', $validated);

        // ✅ Step 1: Check if the service is "BP" (Blood Pressure)
        $bloodPressureValue = null;

        if (!empty($validated['service_id'])) {
            $service = Services::find($validated['service_id']);

            // ✅ Debug: Log service name
            \Log::info("Service Name:", [$service->name ?? "No service found"]);

            // ✅ Correct condition for BP
            if ($service && (strtolower($service->name) === 'bp' || strtolower($service->name) === 'blood pressure')) {
                $bloodPressureValue = $validated['blood_pressure'] ?? null;
            }
        }

        // ✅ Debug: Log blood pressure value before saving
        \Log::info("Blood Pressure Value:", [$bloodPressureValue]);

        // ✅ Step 2: Validate stock availability for all medicines **BEFORE** making any changes
        $insufficientStock = [];
        foreach ($validated['medicines'] ?? [] as $medicineData) {
            $medicine = Medicine::where('medicine_id', $medicineData['medicine_id'])->lockForUpdate()->first();

            if (!$medicine || $medicine->quantity < $medicineData['quantity']) {
                $insufficientStock[] = [
                    'medicine_id' => $medicineData['medicine_id'],
                    'name' => $medicine->name ?? 'Unknown',
                    'requested_quantity' => $medicineData['quantity'],
                    'available_stock' => $medicine->quantity ?? 0,
                ];
            }
        }

        // If any medicine is out of stock, return an error **before making changes**
        if (!empty($insufficientStock)) {
            DB::rollBack();
            return response()->json([
                'error' => 'Insufficient stock for some medicines.',
                'insufficient_stock' => $insufficientStock,
            ], 400);
        }

        // ✅ Step 3: Create transaction record with blood_pressure only if BP service is selected
        $transaction = Transaction::create([
            'citizen_id' => $validated['citizen_id'],
            'service_id' => $validated['service_id'],
            'transaction_date' => now(),
            'blood_pressure' => $bloodPressureValue, // ✅ Ensures BP is saved only for BP service
        ]);

        // ✅ Debug: Log the transaction data
        \Log::info("Saved Transaction:", $transaction->toArray());

        $availedMedicines = [];

        // ✅ Step 4: Process each medicine (now that we know all stock is available)
        foreach ($validated['medicines'] ?? [] as $medicineData) {
            $medicine = Medicine::where('medicine_id', $medicineData['medicine_id'])->lockForUpdate()->first();

            // Deduct stock
            $medicine->quantity -= $medicineData['quantity'];
            $medicine->save();

            // Insert into citizen_medicine table **WITHOUT checking for duplicates**
            DB::table('citizen_medicine')->insert([
                'citizen_id' => $validated['citizen_id'],
                'medicine_id' => $medicine->medicine_id,
                'transaction_id' => $transaction->id,
                'quantity' => $medicineData['quantity'],
                'unit' => $medicineData['unit'], // ✅ Use unit from request, not from medicine table
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $availedMedicines[] = [
                'medicine_id' => $medicine->medicine_id,
                'name' => $medicine->name,
                'availed_quantity' => $medicineData['quantity'],
                'remaining_stock' => $medicine->quantity,
                'unit' => $medicineData['unit'], // ✅ Show correct unit in response
            ];
        }

        DB::commit(); // ✅ Commit transaction

        return response()->json([
            'message' => 'Transaction successfully created!',
            'transaction' => $transaction,
            'availed_medicines' => $availedMedicines,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack(); // ✅ Rollback transaction if an error occurs
        return response()->json(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
    }
}

public function getOverallMedicineAvailed()
{
    $medicines = DB::table('transactions')
        ->join('citizen_medicine', 'transactions.id', '=', 'citizen_medicine.transaction_id')
        ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
        ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id') // Ensure you join citizen details if needed
        ->select('medicine.name', DB::raw('COUNT(citizen_medicine.id) as total_availed')) // Count total availments
        ->groupBy('medicine.name')
        ->orderByDesc('total_availed')
        ->get();

    if ($medicines->isEmpty()) {
        return response()->json(['message' => 'No medicine availment data found'], 404);
    }

    return response()->json($medicines);
}

public function show(Request $request, $citizen_id)
{
    // Fetch citizen details with transactions, ensuring correct relationships
    $citizen = CitizenDetails::with(['transactions.service', 'transactions.medicines'])
        ->find($citizen_id);

    if (!$citizen) {
        return response()->json(['message' => 'Citizen not found'], 404);
    }

    $month = $request->query('month');

    // ✅ Ensure transactions are filtered properly by month
    $transactions = collect($citizen->transactions)
        ->filter(function ($transaction) use ($month) {
            return !$month || date('m', strtotime($transaction->transaction_date)) == $month;
        })
        ->map(function ($transaction) {
            // ✅ Ensure Blood Pressure is only included when applicable
            $isBloodPressureService = optional($transaction->service)->name
                ? (strtolower($transaction->service->name) === 'bp' || strtolower($transaction->service->name) === 'blood pressure')
                : false;

            return [
                'transaction_id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date,
                'service_availed' => optional($transaction->service)->name,
                'blood_pressure' => $isBloodPressureService ? $transaction->blood_pressure : null,
                'medicines_availed' => $transaction->medicines
                    ->map(fn($medicine) => [
                        'medicine_id' => $medicine->medicine_id,
                        'name' => $medicine->name,
                        'quantity' => $medicine->pivot->quantity,
                        'unit' => $medicine->pivot->unit,
                    ])
                    ->unique('medicine_id') // ✅ Ensure each medicine appears only once per transaction
                    ->toArray(),
            ];
        })
        ->values();

    \Log::info("Fetched Transactions for Citizen $citizen_id:", $transactions->toArray()); // ✅ Debug log

    return response()->json([
        'citizen_id' => $citizen->citizen_id,
        'transactions' => $transactions,
    ]);
}

public function getAvailedCitizensByService($service_id)
{
    // Fetch the service by ID
    $service = Services::find($service_id);

    if (!$service) {
        return response()->json(['message' => 'Service not found'], 404);
    }

    $currentUser = auth()->user();
    $isSuperAdmin = $currentUser->role === 'super_admin';

    \Log::info('Current user info', [
        'id' => $currentUser->id,
        'role' => $currentUser->role,
        'brgy' => $currentUser->brgy,
        'municipality' => $currentUser->municipality,
        'province' => $currentUser->province
    ]);

    // Paginate transactions with 'citizenDetails' relation
    $perPage = 10;
    $transactions = Transaction::with('citizenDetails')
        ->where('service_id', $service_id)
        ->orderBy('transaction_date', 'desc')
        ->paginate($perPage);

    if ($transactions->isEmpty()) {
        \Log::info('No transactions found for this service.');
    }

    $citizens = $transactions->getCollection()->map(function ($transaction) use ($currentUser, $isSuperAdmin) {
        $citizen = $transaction->citizenDetails;

        if ($citizen) {
            // Normalize all values for case-insensitive comparison
            $citizenBarangay = strtolower(trim($citizen->barangay));
            $citizenMunicipality = strtolower(trim($citizen->municipality));
            $citizenProvince = strtolower(trim($citizen->province));

            $userBarangay = strtolower(trim($currentUser->brgy));
            $userMunicipality = strtolower(trim($currentUser->municipality));
            $userProvince = strtolower(trim($currentUser->province));

            \Log::info('Checking citizen location match', [
                'citizen_barangay' => $citizenBarangay,
                'user_brgy' => $userBarangay,
                'citizen_municipality' => $citizenMunicipality,
                'user_municipality' => $userMunicipality,
                'citizen_province' => $citizenProvince,
                'user_province' => $userProvince,
            ]);

            if (
                !$isSuperAdmin &&
                (
                    $citizenBarangay !== $userBarangay ||
                    $citizenMunicipality !== $userMunicipality ||
                    $citizenProvince !== $userProvince
                )
            ) {
                \Log::info('Filtered out due to location mismatch', [
                    'citizen_id' => $citizen->id ?? 'N/A',
                    'fullname' => ($citizen->firstname ?? '') . ' ' . ($citizen->lastname ?? '')
                ]);
                return null;
            }

            return [
                'purok' => $citizen->purok ?? "N/A",
                'lastname' => $citizen->lastname ?? "N/A",
                'firstname' => $citizen->firstname ?? "N/A",
                'created_at' => (new \DateTime($transaction->transaction_date))->format('m/d/Y'),
            ];
        }

        \Log::info('Transaction has no citizenDetails', ['transaction_id' => $transaction->id]);
        return null;
    })->filter();

    // Remove duplicates by name
    $uniqueCitizens = $citizens->unique(function ($citizen) {
        return strtolower($citizen['firstname'] . ' ' . $citizen['lastname']);
    })->values();

    if ($uniqueCitizens->isEmpty()) {
        return response()->json([
            'service_name' => $service->name,
            'service_description' => $service->description ?? 'No description available.',
            'citizens' => [['message' => 'No citizen availed this service.']],
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ]
        ]);
    }

    return response()->json([
        'service_name' => $service->name,
        'service_description' => $service->description ?? 'No description available.',
        'citizens' => $uniqueCitizens,
        'pagination' => [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]
    ]);
}
//Admin
public function getMedicineAvailmentByBarangay(Request $request)
{
    try {
        $user = auth()->user();  // Get the logged-in user
        $role = $user->role ?? null;

        // Get the barangay, municipality, and province from the logged-in user
        $barangay = $role === 'superadmin'
            ? $request->query('barangay')
            : ($user->brgy ?? null);

        $municipality = $user->municipality ?? null;
        $province = $user->province ?? null;

        if (!$barangay || !$municipality || !$province) {
            return response()->json([
                'message' => 'Missing required information (barangay, municipality, or province).',
            ], 400);
        }

        // Get all medicines that the logged-in user is associated with
        $medicines = Medicine::all();
        $availmentData = [];

        foreach ($medicines as $medicine) {
            // Query to count each availment occurrence for the medicine in the user's barangay
            $availmentCount = DB::table('citizen_medicine')
                ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
                ->where('citizen_details.barangay', $barangay)
                ->where('citizen_details.municipality', $municipality)
                ->where('citizen_details.province', $province)
                ->where('citizen_medicine.medicine_id', $medicine->medicine_id)
                ->count();  // Count each availment (not distinct citizens)

            // Only include medicines that have any availments for the given barangay
            if ($availmentCount > 0) {
                $availmentData[] = [
                    'id' => $medicine->medicine_id,
                    'name' => $medicine->name,
                    'availment_count' => $availmentCount,
                ];
            }
        }

        return response()->json([
            'barangay' => $barangay,
            'municipality' => $municipality,
            'province' => $province,
            'data' => $availmentData,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching medicine availment data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
//Superadmin
public function getAllMedicineAvailment(Request $request)
{
    try {
        // Get all distinct medicine names
        $medicineNames = Medicine::select('name')->distinct()->pluck('name');

        $availmentData = [];

        foreach ($medicineNames as $name) {
            // Get all medicine IDs with this name
            $medicineIds = Medicine::where('name', $name)->pluck('medicine_id');

            // Count all availments where any of those IDs were used
            $availmentCount = DB::table('citizen_medicine')
                ->whereIn('medicine_id', $medicineIds)
                ->count();

            if ($availmentCount > 0) {
                $availmentData[] = [
                    'name' => $name,
                    'availment_count' => $availmentCount,
                ];
            }
        }

        return response()->json([
            'data' => $availmentData,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching all medicine availment data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


//Profiling
public function filterCitizenTransactionByMonthRange($citizenId, Request $request)
{
    $monthFrom = $request->query('month_from');
    $monthTo = $request->query('month_to');

    if (!$monthFrom || !$monthTo) {
        return response()->json([
            'success' => false,
            'message' => 'Both month_from and month_to are required.'
        ], 400);
    }

    $transactions = Transaction::where('citizen_id', $citizenId)
        ->whereBetween(\DB::raw('MONTH(transaction_date)'), [$monthFrom, $monthTo])
        ->with(['citizenDetails', 'service', 'medicines']) // Eager load citizenDetails, service, and medicines
        ->orderBy('transaction_date', 'asc')
        ->get();

    return response()->json([
        'success' => true,
        'transactions' => $transactions
    ]);
}

// Admin
public function getServiceAvailmentByBarangay(Request $request)
{
    try {
        $user = auth()->user();  // Get the logged-in user
        $role = $user->role ?? null;

        // Get the barangay, municipality, and province from the logged-in user
        $barangay = $role === 'superadmin'
            ? $request->query('barangay')
            : ($user->brgy ?? null);

        $municipality = $user->municipality ?? null;
        $province = $user->province ?? null;

        if (!$barangay || !$municipality || !$province) {
            return response()->json([
                'message' => 'Missing required information (barangay, municipality, or province).',
            ], 400);
        }

        // Get all services (if they are predefined in the `services` table)
        $services = DB::table('services')->get();  // Assuming you have a services table
        if ($services->isEmpty()) {
            return response()->json([
                'message' => 'No services available.',
            ], 404);
        }

        $availmentData = [];

        // Loop through each service and count the number of citizens that availed it
        foreach ($services as $service) {
            // Query the transaction table for service availment data
            $availmentCount = DB::table('transactions')  // Assuming transactions table contains service availment records
                ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
                ->where('citizen_details.barangay', $barangay)
                ->where('citizen_details.municipality', $municipality)
                ->where('citizen_details.province', $province)
                ->where('transactions.service_id', $service->id)  // Ensure you are checking the correct field for service_id
                ->count();  // Count how many transactions occurred for the service in the given barangay

            // Only include services that have any availments
            if ($availmentCount > 0) {
                $availmentData[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'availment_count' => $availmentCount,
                ];
            }
        }

        // Debugging: Log the availment data
        \Log::debug('Service Availment Data:', $availmentData);

        if (empty($availmentData)) {
            return response()->json([
                'message' => 'No availment data found for the given barangay.',
                'barangay' => $barangay,
                'municipality' => $municipality,
                'province' => $province,
            ], 404);
        }

        return response()->json([
            'barangay' => $barangay,
            'municipality' => $municipality,
            'province' => $province,
            'data' => $availmentData,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching service availment data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

//SuperAdmin
public function getAllServiceAvailments()
{
    try {
        // Get all predefined services
        $services = DB::table('services')->get(); // Assuming you have a `services` table

        if ($services->isEmpty()) {
            return response()->json([
                'message' => 'No services available.',
            ], 404);
        }

        $availmentData = [];

        // Loop through each service and count the total availment across all locations
        foreach ($services as $service) {
            $availmentCount = DB::table('transactions')
                ->where('service_id', $service->id)
                ->count();

            if ($availmentCount > 0) {
                $availmentData[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'availment_count' => $availmentCount,
                ];
            }
        }

        // Log for debugging
        \Log::debug('All Service Availment Data:', $availmentData);

        if (empty($availmentData)) {
            return response()->json([
                'message' => 'No availment data found.',
            ], 404);
        }

        return response()->json([
            'data' => $availmentData,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching all service availment data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

//Admin
public function getCitizensByBmi(Request $request)
{
    // Get the authenticated user
    $user = Auth::user();  
    
    // Check if the user is authenticated
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
    }

    // Get BMI classification from the query string
    $bmiCategory = $request->query('classification');  
    
    // Ensure BMI category is provided
    if (!$bmiCategory) {
        return response()->json(['success' => false, 'message' => 'BMI classification not provided'], 400);
    }

    // Fetch citizens with valid height and weight based on user's location
    $citizens = CitizenDetails::where('barangay', $user->barangay)
                              ->where('municipality', $user->municipality)
                              ->where('province', $user->province)
                              ->whereNotNull('height')
                              ->whereNotNull('weight')
                              ->get();

    // Log the raw citizen data for debugging
    \Log::info('Raw Citizens Data', $citizens->toArray());

    // Filter the citizens based on BMI classification
    $filteredCitizens = $citizens->filter(function ($citizen) use ($bmiCategory) {
        $height = floatval($citizen->height) / 100; // Convert cm to meters
        $weight = floatval($citizen->weight);

        if ($height <= 0) return false;  // Skip invalid height

        // Calculate BMI
        $bmi = $weight / ($height * $height);

        // Log BMI value for debugging
        \Log::info("Citizen BMI", ['citizen_id' => $citizen->citizen_id, 'bmi' => $bmi]);

        // Classify and return based on bmiCategory
        if ($bmiCategory === 'Underweight' && $bmi < 18.5) return true;
        if ($bmiCategory === 'Normal' && $bmi >= 18.5 && $bmi <= 24.9) return true;
        if ($bmiCategory === 'Overweight' && $bmi >= 25 && $bmi <= 29.9) return true;
        if ($bmiCategory === 'Obese' && $bmi >= 30) return true;

        return false;
    });

    // Convert the filtered citizens to a proper indexed array
    $filteredCitizensArray = $filteredCitizens->values()->toArray();

    // Log the filtered result for debugging
    \Log::info('Filtered Citizens', $filteredCitizensArray);

    // Return filtered citizens data in the response
    if (count($filteredCitizensArray) > 0) {
        return response()->json(['success' => true, 'data' => $filteredCitizensArray]);
    } else {
        return response()->json(['success' => false, 'message' => 'No citizens found for this BMI classification.'], 404);
    }
}




}
