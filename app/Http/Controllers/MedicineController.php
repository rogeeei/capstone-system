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
    $userId = auth()->user()->user_id;  
    $userBarangay = auth()->user()->brgy;  

    // Retrieve medicines created by the authenticated user and belonging to the same barangay
    $medicines = Medicine::where('user_id', $userId) 
                         ->whereHas('user', function($query) use ($userBarangay) {
                             $query->where('brgy', $userBarangay);  
                         })
                         ->get();

    // Loop through each medicine and update status based on quantity
    foreach ($medicines as $medicine) {
        if ($medicine->quantity > 0) {
            $medicine->medicine_status = 'Available';
        } else {
            $medicine->medicine_status = 'Out of Stock';
        }
        $medicine->save(); // Save each medicine after updating status
    }

    // Return the updated medicines data as a JSON response
    return response()->json($medicines);
}


public function getAvailableMedicines()
{
    $userId = auth()->user()->user_id;  
    $userBarangay = auth()->user()->brgy;  

    // Retrieve medicines created by the authenticated user and belonging to the same barangay, excluding those with 0 quantity
    $medicines = Medicine::where('user_id', $userId) 
                         ->whereHas('user', function($query) use ($userBarangay) {
                             $query->where('brgy', $userBarangay);  
                         })
                         ->where('quantity', '>', 0) // Exclude medicines with 0 quantity
                         ->get();

    foreach ($medicines as $medicine) {
        $medicine->medicine_status = 'Available';
        $medicine->save(); // Save updated status
    }

    return response()->json($medicines);
}

public function getMedicinesByBarangay()
{
    try {
        // ✅ Fetch medicines grouped by barangay
        $medicinesByBarangay = User::join('medicine', 'users.user_id', '=', 'medicine.user_id')
            ->select(
                'users.brgy as barangay',
                'medicine.name as medicine_name',
                'medicine.unit as unit',
                DB::raw('SUM(medicine.quantity) as total_quantity')
            )
            ->whereNotNull('users.brgy') 
            ->groupBy('users.brgy', 'medicine.name', 'medicine.unit') // ✅ Group by unit
            ->orderBy('users.brgy') // ✅ Order by barangay
            ->get();

        if ($medicinesByBarangay->isEmpty()) {
            return response()->json(["message" => "No medicines found"], 404);
        }

        // ✅ Transform the data into a structured format
        $groupedData = [];
        foreach ($medicinesByBarangay as $item) {
            $barangay = $item->barangay;

            if (!isset($groupedData[$barangay])) {
                $groupedData[$barangay] = [
                    'barangay' => $barangay,
                    'medicines' => [],
                ];
            }

            $groupedData[$barangay]['medicines'][] = [
                'name' => $item->medicine_name,
                'total_quantity' => $item->total_quantity,
                'unit' => $item->unit, // ✅ Include unit in response
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
    // Retrieve the validated input data...
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
    $validated['user_id'] = auth()->user()->user_id;  // Store the user_id from the logged-in user

    // Set default status based on quantity
    $validated['medicine_status'] = ($validated['quantity'] > 0) ? 'Available' : 'Out of Stock';

    // Create the medicine record with the generated medicine_id and user_id
    $medicine = Medicine::create($validated);

    // Check if the quantity is 1 or more, then reduce by 1
    if ($medicine->quantity > 0) {
        $medicine->quantity -= 1; // Reduce by 1
    }

    // After reducing quantity, check if it reached 0
    if ($medicine->quantity <= 0) {
        $medicine->medicine_status = 'Out of Stock';
    } else {
        $medicine->medicine_status = 'Available';
    }

    // Save updated quantity and status
    $medicine->save();

    return response()->json($medicine);
}




public function updateMedicineStock(Request $request, $medicine_id)
{
    // Validate incoming request
    $validated = $request->validate([
        'quantity' => 'required|integer|min:1',
        'unit' => 'required|string',
        'date_acquired' => 'required|date',
    ]);

    try {
        // Find the medicine (throws 404 if not found)
        $medicine = Medicine::where('medicine_id', $medicine_id)->firstOrFail();

        // Update the quantity using increment (more efficient)
        $medicine->increment('quantity', $validated['quantity']);
        
        // Update the unit if changed
        if ($medicine->unit !== $validated['unit']) {
            $medicine->update(['unit' => $validated['unit']]);
        }

        return response()->json([
            'message' => 'Medicine stock updated successfully',
            'medicine' => $medicine->fresh(), // Ensure updated data is returned
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while updating medicine stock',
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
        'batch_no'             => 'nullable|string|max:255',
        'location'             => 'nullable|string|max:255',
        'quantity'             => 'nullable|integer|min:0', // ✅ Ensure quantity is allowed
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
