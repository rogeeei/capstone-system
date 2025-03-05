<?php

namespace App\Http\Controllers;

use App\Models\Services;
use App\Models\CitizenDetails;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class ServicesController extends Controller
{
   public function index()
{
    // Fetch all services
    $services = Services::all();

    // Return services as JSON
    return response()->json($services, 200);
}
public function getServices()
{
    // Fetch distinct services by name
    $services = Services::select('name')->groupBy('name')->get();

    // Append citizens who availed each service
    $services->transform(function ($service) {
        $service->citizens_availed = DB::table('transactions') // ✅ Use 'transactions' table
            ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
            ->whereIn('transactions.service_id', function ($query) use ($service) {
                $query->select('id')->from('services')->where('name', $service->name);
            })
            ->select(
                'citizen_details.citizen_id',
                'citizen_details.firstname',
                'citizen_details.lastname',
                'citizen_details.barangay'
            )
            ->distinct() // ✅ Prevent duplicate citizens in response
            ->get();

        return $service;
    });

    return response()->json($services, 200);
}


public function getServicesByBarangay()
{
    // ✅ Get the authenticated user
    $user = Auth::user();
    
    if (!$user || !$user->brgy) {
        return response()->json(['message' => 'User barangay not found.'], 404);
    }

    // ✅ Fetch only services created by users in the same barangay
    $services = Services::whereHas('user', function ($query) use ($user) {
        $query->where('brgy', $user->brgy);
    })->get();

    return response()->json($services, 200);
}
public function getCitizenServicesByBarangay(Request $request)
{
    // ✅ Get the barangay from the query parameter
    $barangay = $request->query('barangay');
    
    if (!$barangay) {
        return response()->json(['message' => 'Barangay not specified.'], 400);
    }

    // ✅ Fetch services based on the barangay parameter
    $services = Services::whereHas('user', function ($query) use ($barangay) {
        $query->where('brgy', $barangay); // Filter by barangay
    })->get();

    // ✅ Return services
    return response()->json($services, 200);
}

         public function store(Request $request)
    {
        // ✅ Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // ✅ Create a new service and assign user_id
        $service = new Services();
        $service->name = $request->name;
        $service->description = $request->description;
        $service->user_id = Auth::user()->user_id; // ✅ Assign logged-in user's user_id
        $service->save();

        return response()->json([
            'message' => 'Service created successfully!',
            'service' => $service
        ], 201);
    }
    /**
     * Show the service summary.
     *
     * @return \Illuminate\View\View
     */
    public function showServicesSummary()
    {
        try {
            // Fetch all services with the count of citizens who availed each service
            $services = Services::all()->map(function ($service) {
                try {
                    $service->citizens_count = $service->citizens()->count(); // Count citizens per service
                } catch (\Exception $e) {
                    Log::error("Error counting citizens for service {$service->name}: " . $e->getMessage());
                    $service->citizens_count = 0; // Set default value in case of error
                }
                return $service;
            });

            // Return the services as a JSON response
            return response()->json($services);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching service summary: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'An error occurred while fetching the service summary.'], 500);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $services = Services::findOrFail($id);

        $services->delete();

        return $services;
    }
}
