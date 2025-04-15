<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipmentRequest;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\User; 


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
        \Log::info("Fetching equipment by barangay with full location...");

        // ✅ Check if Equipment Table is Empty
        if (Equipment::count() === 0) {
            return response()->json(["message" => "No equipment data exists."], 200);
        }

        // ✅ Join with users and group by location
        $equipmentByBarangay = User::join('equipment', 'users.user_id', '=', 'equipment.user_id')
            ->select(
                'users.brgy as barangay',
                'users.municipality as municipality',
                'users.province as province',
                'equipment.name as equipment_name',
                DB::raw('SUM(equipment.quantity) as total_quantity')
            )
            ->whereNotNull('users.brgy')
            ->groupBy(
                'users.brgy',
                'users.municipality',
                'users.province',
                'equipment.name'
            )
            ->orderBy('users.brgy')
            ->get();

        if ($equipmentByBarangay->isEmpty()) {
            return response()->json(["message" => "No equipment found in any barangay."], 200);
        }

        // ✅ Group and structure the data
        $groupedData = [];
        foreach ($equipmentByBarangay as $item) {
            $key = $item->barangay . '|' . $item->municipality . '|' . $item->province;

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'barangay' => $item->barangay,
                    'municipality' => $item->municipality,
                    'province' => $item->province,
                    'equipment' => [],
                ];
            }

            $groupedData[$key]['equipment'][] = [
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


public function index(Request $request)
{
    // Get the authenticated user's barangay (brgy)
    $userBarangay = auth()->user()->brgy;  // Get the user's barangay

    // Retrieve equipment created by users from the same barangay
    $query = Equipment::whereHas('user', function($query) use ($userBarangay) {
        // Ensure the equipment belongs to the same barangay as the logged-in user
        $query->where('brgy', $userBarangay);
    });

    // If a search query is provided, filter the equipment based on search
    if ($request->has('search') && !empty($request->search)) {
        $searchTerm = $request->search;

        $query->where(function ($q) use ($searchTerm) {
            $q->where('equipment_id', 'LIKE', '%' . $searchTerm . '%')
              ->orWhere('name', 'LIKE', '%' . $searchTerm . '%')
              ->orWhere('quantity', 'LIKE', '%' . $searchTerm . '%')
              ->orWhere('condition', 'LIKE', '%' . $searchTerm . '%')
              ->orWhere('date_acquired', 'LIKE', '%' . $searchTerm . '%');
        });
    }

    // Sorting by column and order (with defaults)
    $column = $request->input('column', 'name');  // Default to sorting by 'name'
    $order = $request->input('order', 'asc');     // Default to ascending order
    $query->orderBy($column, $order);

    // Retrieve the filtered and sorted equipment
    $equipment = $query->get();

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
