<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\CitizenDetails;
use App\Models\Services;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;



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
        $medicines = Medicine::all();
        $availmentData = [];

        foreach ($medicines as $medicine) {
            // Count all availments of the medicine (regardless of location)
            $availmentCount = DB::table('citizen_medicine')
                ->where('medicine_id', $medicine->medicine_id)
                ->count();

            // Only include medicines that have at least one availment
            if ($availmentCount > 0) {
                $availmentData[] = [
                    'id' => $medicine->medicine_id,
                    'name' => $medicine->name,
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







}
