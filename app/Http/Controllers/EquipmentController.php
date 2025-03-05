<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipmentRequest;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;


class EquipmentController extends Controller
{

public function updateEquipmentStock(Request $request, $equipment_id): JsonResponse
{
    try {
        // Validate request
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'date_acquired' => 'required|date',
        ]);

        // Find equipment
        $equipment = Equipment::where('equipment_id', $equipment_id)->firstOrFail();

        // ✅ Add the new quantity to the existing quantity
        $newQuantity = $equipment->quantity + $validated['quantity'];

        // ✅ Update the quantity and date_acquired
        $equipment->update([
            'quantity' => $newQuantity,
            'date_acquired' => $validated['date_acquired'],
        ]);

        return response()->json([
            'message' => 'Equipment stock updated successfully',
            'equipment' => $equipment->fresh(),
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while updating equipment stock',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function getEquipmentByBarangay()
{
    try {
        \Log::info("Fetching equipment by barangay...");

        // ✅ Check if Equipment Table is Empty
        if (Equipment::count() === 0) {
            return response()->json(["message" => "No equipment data exists."], 200);
        }

        // ✅ Fetch equipment grouped by barangay
        $equipmentByBarangay = Equipment::leftJoin('users', 'equipment.user_id', '=', 'users.user_id')
            ->select(
                'users.brgy as barangay',
                'equipment.name as equipment_name',
                DB::raw('SUM(equipment.quantity) as total_quantity')
            )
            ->whereNotNull('users.brgy') 
            ->groupBy('users.brgy', 'equipment.name')
            ->orderBy('users.brgy')
            ->get();

        // ✅ Log fetched data
        \Log::info("Equipment data retrieved:", ['data' => $equipmentByBarangay]);

        if ($equipmentByBarangay->isEmpty()) {
            return response()->json(["message" => "No equipment found in any barangay."], 200);
        }

        // ✅ Transform the data into a structured format
        $groupedData = [];
        foreach ($equipmentByBarangay as $item) {
            $barangay = $item->barangay ?? 'Unknown'; // ✅ Handle null barangays

            if (!isset($groupedData[$barangay])) {
                $groupedData[$barangay] = [
                    'barangay' => ucfirst($barangay),
                    'equipment' => [],
                ];
            }

            $groupedData[$barangay]['equipment'][] = [
                'name' => $item->equipment_name,
                'total_quantity' => $item->total_quantity,
            ];
        }

        return response()->json(array_values($groupedData));
    } catch (\Exception $e) {
        \Log::error("Error fetching equipment by barangay:", ['error' => $e->getMessage()]);
        return response()->json([
            'message' => 'An error occurred while fetching equipment.',
            'error' => $e->getMessage(),
        ], 500);
    }
}





    public function index()
{
    // Get the authenticated user's ID and barangay (brgy) from the users table
    $userId = auth()->user()->user_id;  // Assuming you're using user_id as the unique identifier
    $userBarangay = auth()->user()->brgy;  // Get the user's barangay

    // Retrieve equipment created by the authenticated user and belonging to the same barangay
    $equipment = Equipment::where('user_id', $userId)  // Only fetch equipment created by the logged-in user
                          ->whereHas('user', function($query) use ($userBarangay) {
                              // Ensure the equipment belongs to the same barangay as the logged-in user
                              $query->where('brgy', $userBarangay);  
                          })
                          ->get();

    // Return the equipment data as a JSON response
    return response()->json($equipment);
}

    /**
     * Store a newly created resource in storage.
     */
 public function store(EquipmentRequest $request)
{
    try {
        // Retrieve the validated input data
        $validated = $request->validated();

        // Get the last equipment_id that starts with '100-'
        $lastEquipment = Equipment::where('equipment_id', 'like', '100-%')
            ->orderBy('equipment_id', 'desc')
            ->first();

        // Extract the incrementing number and format a new ID
        $lastNumber = $lastEquipment ? (int) substr($lastEquipment->equipment_id, 4) : 0;
        $newEquipmentId = '100-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        // Assign the generated equipment_id
        $validated['equipment_id'] = $newEquipmentId;
        $validated['user_id'] = auth()->user()->user_id;  // Store the user_id from the logged-in user

        // Create the equipment record
        $equipment = Equipment::create($validated);

        // Return the newly created equipment
        return response()->json([
            'message' => 'Equipment added successfully',
            'equipment' => $equipment,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error occurred: ' . $e->getMessage(),
        ], 500);
    }
}


public function show(string $id)
{
    // Find the equipment by ID or fail
    $equipment = Equipment::findOrFail($id);

    // Return the equipment data
    return response()->json($equipment);
}

public function update(Request $request, string $id)
{
    $validated = $request->validate([
        'name' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:255',
        'quantity' => 'nullable|integer',
        'location' => 'nullable|string|max:255',
        'condition' => 'nullable|string|max:255',
        'date_acquired' => 'nullable|date',
    ]);

    $equipment = Equipment::findOrFail($id);

    // ✅ Remove quantity if not provided to prevent accidental update
    if (!isset($validated['quantity'])) {
        unset($validated['quantity']);
    }

    $equipment->update($validated);

    return response()->json([
        'message' => 'Equipment updated successfully',
        'equipment' => $equipment
    ]);
}



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $equipment = Equipment::findOrFail($id);

        $equipment->delete();

        return $equipment;
    }
}
