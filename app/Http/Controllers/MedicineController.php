<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\MedicineRequest;
use App\Models\CitizenDetails;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log;

class MedicineController extends Controller
{
public function index()
{
    $userBarangay = auth()->user()->brgy; // Get authenticated user's barangay

    // ✅ Retrieve all medicines where ANY user from the same barangay created them
    $medicines = Medicine::whereHas('user', function ($query) use ($userBarangay) {
        $query->where('users.brgy', $userBarangay); // Fetch by barangay
    })->get();

    // ✅ Modify `medicine_status` dynamically (DO NOT save in DB inside GET requests)
    $medicines->transform(function ($medicine) {
        $medicine->medicine_status = $medicine->quantity > 0 ? 'Available' : 'Out of Stock';
        return $medicine;
    });

    // ✅ Return medicines from the same barangay
    return response()->json($medicines);
}



public function getAvailableMedicines()
{
    $userBarangay = auth()->user()->brgy;

    // Step 1: Get all valid medicines from the same barangay with quantity > 0
    $medicines = Medicine::whereHas('user', function ($query) use ($userBarangay) {
            $query->where('users.brgy', $userBarangay);
        })
        ->where('quantity', '>', 0)
        ->orderBy('created_at', 'asc') // Important: to get the oldest entry first
        ->get();

    // Step 2: Filter to keep only the first medicine per unique name
    $uniqueMedicines = $medicines->unique('name')->values();

    // Step 3: Add status (without modifying DB)
    $uniqueMedicines->transform(function ($medicine) {
        $medicine->medicine_status = 'Available';
        return $medicine;
    });

    return response()->json($uniqueMedicines);
}



public function getMedicinesByBarangay()
{
    try {
        // Join medicine with users and fetch all location info, including expiration_date
        $medicinesByBarangay = User::join('medicine', 'users.user_id', '=', 'medicine.user_id')
            ->select(
                'users.brgy as barangay',
                'users.municipality as municipality',
                'users.province as province',
                'medicine.name as medicine_name',
                'medicine.unit as unit',
                'medicine.expiration_date as expiration_date', // Added expiration date
                DB::raw('SUM(medicine.quantity) as total_quantity')
            )
            ->whereNotNull('users.brgy')
            ->groupBy(
                'users.brgy',
                'users.municipality',
                'users.province',
                'medicine.name',
                'medicine.unit',
                'medicine.expiration_date' // Added expiration_date to the groupBy
            )
            ->orderBy('users.brgy')
            ->get();

        if ($medicinesByBarangay->isEmpty()) {
            return response()->json(["message" => "No medicines found"], 404);
        }

        // Group by barangay (with full location) and list medicines inside
        $groupedData = [];

        foreach ($medicinesByBarangay as $item) {
            $key = $item->barangay . '|' . $item->municipality . '|' . $item->province;

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'barangay' => $item->barangay,
                    'municipality' => $item->municipality,
                    'province' => $item->province,
                    'medicines' => [],
                ];
            }

            $groupedData[$key]['medicines'][] = [
                'name' => $item->medicine_name,
                'total_quantity' => $item->total_quantity,
                'unit' => $item->unit,
                'expiration_date' => $item->expiration_date, // Added expiration date
            ];
        }

        return response()->json(array_values($groupedData));
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching medicines.',
            'error' => $e->getMessage(),
        ], 500);
    }
}








public function getMedicine()
{
    // Fetch all medicine
    $medicine = Medicine::all();

    // Return medicine as JSON
    return response()->json($medicine, 200);
}


    /**
     * Store a newly created resource in storage.
     */
public function store(MedicineRequest $request)
{
    // Retrieve the validated input data
    $validated = $request->validated();

    // Get the last medicine_id that starts with '100-'
    $lastMedicine = Medicine::where('medicine_id', 'like', '100-%')
        ->orderBy('medicine_id', 'desc')
        ->first();

    // Extract the incrementing number, remove the '100-' prefix
    $lastNumber = $lastMedicine ? (int) substr($lastMedicine->medicine_id, 4) : 0;

    // Increment the number and format it with leading zeros
    $newMedicineId = '100-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

    // Assign the generated medicine_id and user_id (current logged-in user)
    $validated['medicine_id'] = $newMedicineId;
    $validated['user_id'] = auth()->user()->user_id;

    // Set default status based on quantity
    $validated['medicine_status'] = ($validated['quantity'] > 0) ? 'Available' : 'Out of Stock';

    // Create the medicine record
    $medicine = Medicine::create($validated);

    return response()->json($medicine);
}

//Admin Report
public function getMonthlyMedicineAvailed()
{
    // Count how many times each medicine was availed per month
    $monthlyMedicineAvailed = DB::table('citizen_medicine')
        ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
        ->select(
            DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%M %Y') as month"), // ✅ Month as a word
            DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%Y-%m') as month_order"), // ✅ Sortable format
            'medicine.name as medicine_name',
            DB::raw("COUNT(citizen_medicine.medicine_id) as total_availed")
        )
        ->groupBy('month', 'month_order', 'medicine.name')
        ->orderBy('month_order', 'ASC') 
        ->orderByDesc('total_availed')
        ->get();

    return response()->json($monthlyMedicineAvailed);
}
//User Report
public function getBarangayMonthlyMedicineAvailed()
{
    $userBarangay = auth()->user()->brgy; // ✅ Get the logged-in user's barangay

    // Count how many times each medicine was availed per month, filtered by barangay
    $monthlyMedicineAvailed = DB::table('citizen_medicine')
        ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
        ->join('users', 'medicine.user_id', '=', 'users.user_id') // ✅ Ensure medicine belongs to the same barangay
        ->where('users.brgy', $userBarangay) // ✅ Filter by logged-in user's barangay
        ->select(
            DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%M %Y') as month"), // ✅ Month as a word
            DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%Y-%m') as month_order"), // ✅ Sortable format
            'medicine.name as medicine_name',
            DB::raw("COUNT(citizen_medicine.medicine_id) as total_availed")
        )
        ->groupBy('month', 'month_order', 'medicine.name')
        ->orderBy('month_order', 'ASC') 
        ->orderByDesc('total_availed')
        ->get();

    return response()->json($monthlyMedicineAvailed);
}


//used
public function getMonthlyMedicineAvailedByBarangay(Request $request)
{
    try {
        $barangay = $request->query('barangay'); // ✅ Get barangay from query parameter

        if (!$barangay) {
            return response()->json([
                'success' => false,
                'message' => 'Barangay parameter is required.'
            ], 400);
        }

        // ✅ Fetch medicine availed by specific barangay
        $monthlyMedicineAvailed = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.barangay', $barangay) // ✅ Filter by barangay
            ->select(
                DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%M %Y') as month"),
                DB::raw("DATE_FORMAT(citizen_medicine.created_at, '%Y-%m') as month_order"),
                'medicine.name as medicine_name',
                DB::raw("COUNT(citizen_medicine.medicine_id) as total_availed")
            )
            ->groupBy('month', 'month_order', 'medicine.name')
            ->orderBy('month_order', 'ASC') // ✅ Orders by actual month (YYYY-MM)
            ->orderByDesc('total_availed')
            ->get();

        if ($monthlyMedicineAvailed->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No medicine availed data found for barangay: $barangay."
            ], 404);
        }

        return response()->json([
            'success' => true,
            'barangay' => $barangay,
            'data' => $monthlyMedicineAvailed
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ], 500);
    }
}






public function duplicateMedicineStock(Request $request, $medicine_id)
{
    $validated = $request->validate([
        'quantity' => 'required|integer|min:1',
        'date_acquired' => 'required|date',
        'expiration_date' => 'required|date',
        'batch_no' => 'nullable|string',
    ]);

    try {
        // Get the original medicine
        $original = Medicine::where('medicine_id', $medicine_id)->firstOrFail();

        // Generate a new unique ID for the duplicate (e.g., incremented manually)
        $lastId = Medicine::max('medicine_id'); // e.g., "100-004"
        [$prefix, $number] = explode('-', $lastId);
        $newId = sprintf('%s-%03d', $prefix, intval($number) + 1);

        // Create new row with copied and new data
        $newMedicine = Medicine::create([
            'medicine_id' => $newId,
            'name' => $original->name,
            'usage_description' => $original->usage_description,
            'unit' => $original->unit,
            'medicine_status' => $original->medicine_status ?? 'available',
            'user_id' => $original->user_id, // assuming you want to copy this as well
            'quantity' => $validated['quantity'],
            'expiration_date' => $validated['expiration_date'],
            'date_acquired' => $validated['date_acquired'],
            'batch_no' => $validated['batch_no'] ?? null,
        ]);

        return response()->json([
            'message' => 'Medicine duplicated as new stock entry',
            'medicine' => $newMedicine,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error duplicating medicine entry',
            'error' => $e->getMessage(),
        ], 500);
    }
}





public function availMedicine(CitizenDetails $citizen, Request $request)
{
    // Validate the incoming data, including the medicine IDs and quantity
    $validated = $request->validate([
        'medicines' => 'required|array',
        'medicines.*.medicine_id' => 'required|exists:medicines,medicine_id',
        'medicines.*.quantity' => 'required|integer|min:1',
    ]);

    $availedMedicines = [];

    foreach ($validated['medicines'] as $medicineData) {
        $medicine = Medicine::where('medicine_id', $medicineData['medicine_id'])->first();

        if ($medicine && $medicine->quantity >= $medicineData['quantity']) {
            // Attach medicine to citizen
            $citizen->medicines()->attach($medicine->id, ['quantity' => $medicineData['quantity']]);

            // Decrease medicine stock
            $medicine->quantity -= $medicineData['quantity'];
            $medicine->save();

            $availedMedicines[] = [
                'medicine_id' => $medicine->medicine_id,
                'name' => $medicine->name,
                'availed_quantity' => $medicineData['quantity'],
                'remaining_stock' => $medicine->quantity,
            ];
        } else {
            return response()->json(['error' => 'Not enough stock for ' . $medicine->name], 400);
        }
    }

    return response()->json([
        'message' => 'Medicine availed successfully',
        'availed_medicines' => $availedMedicines
    ]);
}

    /**
     * Display the specified resource.
     */
  public function show($id)
{
    $medicine = Medicine::where('medicine_id', $id)->first();

    if (!$medicine) {
        return response()->json(['message' => 'Medicine not found'], 404);
    }

    return response()->json($medicine);
}


  /**
 * Update the specified medicine in storage.
 */
public function update(Request $request, string $id)
{
    // Validate request
    $validated = $request->validate([
        'name'                 => 'nullable|string|max:255',
        'usage_description'    => 'nullable|string|max:255',
       
        'quantity'             => 'nullable|integer|min:0', 
    ]);

    // Find the medicine by ID
    $medicine = Medicine::find($id);

    if (!$medicine) {
        return response()->json(['message' => 'Medicine not found'], 404);
    }

    // Update medicine details
    $medicine->update($validated);

    //  Check quantity and update status automatically
    if (isset($validated['quantity'])) { 
        if ($validated['quantity'] > 0) {
            $medicine->medicine_status = 'Available';
        } else {
            $medicine->medicine_status = 'Out of Stock';
        }
    }

    // Save the updated medicine
    $medicine->save();

    return response()->json(['message' => 'Medicine updated successfully', 'medicine' => $medicine]);
}




}
