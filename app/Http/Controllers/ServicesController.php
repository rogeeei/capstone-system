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
    $userBarangay = auth()->user()->brgy; // Get the logged-in user's barangay

    // Retrieve services assigned to the user's barangay
    $services = DB::table('barangay_services')
        ->join('services', 'barangay_services.service_id', '=', 'services.id')
        ->where('barangay_services.brgy', $userBarangay)
        ->select('services.id', 'services.name', 'services.icon') // ✅ Select the icon too
        ->get();

    return response()->json($services);
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
  $request->validate([
    'services' => 'required|array|min:1',
    'services.*.name' => 'required|string|max:255',
    'services.*.description' => 'nullable|string',
    'services.*.icon' => 'nullable|string',
]);


    // ✅ Get authenticated user
    $user = Auth::user();
    if (!$user || $user->role !== 'super_admin') {
        return response()->json(['message' => 'Unauthorized - Only super admins can create services'], 403);
    }

    // ✅ Store each service
    $createdServices = [];
    foreach ($request->services as $serviceData) {
        $service = new Services();
        $service->name = $serviceData['name'];
        $service->description = $serviceData['description'] ?? null;
        $service->icon = $serviceData['icon'] ?? null;
        $service->save();

        $createdServices[] = $service;
    }

    return response()->json([
        'message' => 'Services created successfully!',
        'services' => $createdServices
    ], 201);
}


public function assignServiceToBarangay(Request $request)
{
    $request->validate([
        'services' => 'required|array|min:1',
        'services.*.service_id' => 'required|exists:services,id',
    ]);

    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $barangay = $user->brgy;
    if (!$barangay) {
        return response()->json(['message' => 'User has no assigned barangay'], 400);
    }

    foreach ($request->services as $serviceData) {
        $serviceId = $serviceData['service_id'];

        // Check if the service is already assigned to the barangay
        $exists = DB::table('barangay_services')
            ->where('brgy', $barangay)
            ->where('service_id', $serviceId)
            ->exists();

        if (!$exists) {
            DB::table('barangay_services')->insert([
                'brgy' => $barangay,
                'service_id' => $serviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return response()->json(['message' => 'Services assigned successfully']);
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

    
public function getServiceAvailmentStats(Request $request)
{
    // Parse date range from query parameters
    $from = $request->query('from');
    $to   = $request->query('to');

    $query = DB::table('transactions')
        ->join('services', 'transactions.service_id', '=', 'services.id')
        ->select('services.name', DB::raw('COUNT(DISTINCT transactions.citizen_id) as citizen_count'))
        ->groupBy('services.name')
        ->orderByDesc('citizen_count');

    // Apply date filter if both 'from' and 'to' are provided
    if ($from && $to) {
        try {
            $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
            $toDate   = \Carbon\Carbon::parse($to)  ->endOfDay();

            $query->whereBetween('transactions.created_at', [$fromDate, $toDate]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid date format for "from" or "to". Use YYYY-MM-DD.'
            ], 400);
        }
    }

    $serviceStats = $query->get();

    if ($serviceStats->isEmpty()) {
        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'No service availment data found for the given range'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data'    => $serviceStats
    ]);
}



}
