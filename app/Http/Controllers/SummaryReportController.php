<?php

namespace App\Http\Controllers;

use App\Models\CitizenDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Services;
use App\Models\Transaction;



class SummaryReportController extends Controller
{
    public function getDemographicSummary()
{
    try {
        // **Age group calculation**
        $ageGroups = CitizenDetails::selectRaw("
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, COUNT(DISTINCT citizen_id) as count
        ")
        ->whereNotNull('date_of_birth') // Ensure date_of_birth is valid
        ->groupBy('age_group')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->age_group => $item->count];
        });

        // **Gender distribution**
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(DISTINCT citizen_id) as count
        ")
        ->whereIn('gender', ['Male', 'male', 'Female', 'female', 'M', 'm', 'F', 'f']) // Ensure only valid genders are included
        ->groupBy('gender')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->gender => $item->count];
        });

        // **Total population count**
        $totalPopulation = CitizenDetails::distinct('citizen_id')->count();

        // **Validation to ensure consistency**
        $sumGenderDistribution = array_sum($genderDistribution->toArray());
        if ($sumGenderDistribution !== $totalPopulation) {
            Log::warning('Gender distribution does not match the total population.');
        }

        // **Return the demographic summary as JSON**
        return response()->json([
            'ageGroups' => $ageGroups,
            'genderDistribution' => $genderDistribution,
            'totalPopulation' => $totalPopulation
        ]);
    } catch (\Exception $e) {
        // Log error and return a 500 response with error message
        Log::error('Failed to fetch demographic summary: ' . $e->getMessage());
        return response()->json(['error' => 'Unable to fetch demographic summary data'], 500);
    }
}

// Admin Report JS
public function getDemographicSummaryByBarangay($barangay)
{
    try {
        // **Age group calculation per barangay**
        $ageGroups = CitizenDetails::selectRaw("
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, COUNT(DISTINCT citizen_id) as count
        ")
        ->where('barangay', $barangay) // Filter by barangay
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->age_group => $item->count];
        });

        // **Gender distribution per barangay**
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(DISTINCT citizen_id) as count
        ")
        ->where('barangay', $barangay) // Filter by barangay
        ->whereIn('gender', ['Male', 'male', 'Female', 'female', 'M', 'm', 'F', 'f'])
        ->groupBy('gender')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->gender => $item->count];
        });

        // **Total population count per barangay**
        $totalPopulation = CitizenDetails::where('barangay', $barangay)
            ->distinct('citizen_id')
            ->count();

        return response()->json([
            'barangay' => $barangay,
            'ageGroups' => $ageGroups,
            'genderDistribution' => $genderDistribution,
            'totalPopulation' => $totalPopulation
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to fetch demographic summary for barangay: ' . $e->getMessage());
        return response()->json(['error' => 'Unable to fetch demographic summary data'], 500);
    }
}

//Reports JS
public function getServiceWithAgeDistribution($serviceName)
{
    try {
        // ✅ Fetch all service IDs that have the same name
        $serviceIds = Services::where('name', $serviceName)->pluck('id');

        if ($serviceIds->isEmpty()) {
            return response()->json([
                'message' => 'No services found with the given name.',
                'serviceName' => $serviceName,
                'ageGroups' => [],
                'totalCitizens' => 0,
            ], 404);
        }

        // ✅ Fetch all citizen IDs from `transactions`, not `citizen_services`
        $citizenIds = DB::table('transactions')
            ->whereIn('service_id', $serviceIds)
            ->pluck('citizen_id')
            ->filter();

        if ($citizenIds->isEmpty()) {
            return response()->json([
                'serviceName' => $serviceName,
                'ageGroups' => [],
                'totalCitizens' => 0,
            ]);
        }

        // ✅ Count age group distribution
        $ageGroups = DB::table('citizen_details')
            ->selectRaw("
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                    ELSE 'Elderly' 
                END as age_group, 
                COUNT(*) as count
            ")
            ->whereIn('citizen_id', $citizenIds)
            ->groupBy('age_group')
            ->get();

        return response()->json([
            'serviceName' => $serviceName,
            'ageGroups' => $ageGroups->pluck('count', 'age_group'),
            'totalCitizens' => $ageGroups->sum('count'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching the service data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



//User Report JS 
public function getServiceWithAgeDistributionByBarangay(Request $request, $serviceId)
{
    try {
        $user = auth()->user(); // ✅ Get authenticated user
        $role = $user->role ?? null; // ✅ Get user role

        // ✅ If super admin, get barangay from query parameter
        if ($role === 'superadmin') {
            $barangay = $request->query('barangay');
        } else {
            $barangay = $user->brgy ?? null; // ✅ Regular users get their own barangay
        }

        if (!$barangay) {
            return response()->json([
                'message' => 'Barangay parameter is missing.',
            ], 400);
        }

        // ✅ Fetch the service
        $service = Services::findOrFail($serviceId);

        // ✅ Fetch citizen IDs from transactions (filtered by barangay)
        $citizenIds = Transaction::where('service_id', $serviceId)
            ->whereHas('citizen', function ($query) use ($barangay) {
                $query->where('barangay', $barangay);
            })
            ->pluck('citizen_id')
            ->filter();

        if ($citizenIds->isEmpty()) {
            return response()->json([
                'serviceName' => $service->name,
                'barangay' => $barangay,
                'ageGroups' => [],
                'totalCitizens' => 0,
            ]);
        }

        // ✅ Fetch age group distribution
        $ageGroups = CitizenDetails::selectRaw("
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, 
            COUNT(*) as count
        ")
        ->whereIn('citizen_id', $citizenIds)
        ->groupBy('age_group')
        ->get();

        return response()->json([
            'serviceName' => $service->name,
            'barangay' => $barangay,
            'ageGroups' => $ageGroups->pluck('count', 'age_group'),
            'totalCitizens' => $ageGroups->sum('count'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching the service data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


//Admin Report JS
public function getAdminServiceWithAgeDistributionByBarangay(Request $request, $serviceName)
{
    try {
        // ✅ Normalize service name (remove spaces and make lowercase)
        $serviceName = trim(strtolower($serviceName));

        // ✅ Read barangay from request parameter
        $barangay = $request->query('barangay');

        if (!$barangay) {
            return response()->json([
                'message' => 'Barangay parameter is required.'
            ], 400);
        }

        // ✅ Fetch all services with this name (case-insensitive)
        $serviceIds = Services::whereRaw('LOWER(TRIM(name)) = ?', [$serviceName])
            ->pluck('id');

        if ($serviceIds->isEmpty()) {
            return response()->json([
                'message' => "No service found with name: $serviceName",
                'serviceName' => $serviceName,
                'barangay' => $barangay,
                'ageGroups' => [],
                'totalCitizens' => 0
            ], 404);
        }

        // ✅ Fetch citizen IDs who availed any of these services in the barangay
        $citizenIds = Transaction::whereIn('service_id', $serviceIds)
            ->whereHas('citizen', function ($query) use ($barangay) {
                $query->whereRaw('LOWER(TRIM(barangay)) = ?', [strtolower(trim($barangay))]);
            })
            ->pluck('citizen_id')
            ->unique(); // ✅ Remove duplicates

        if ($citizenIds->isEmpty()) {
            return response()->json([
                'serviceName' => $serviceName,
                'barangay' => $barangay,
                'ageGroups' => [],
                'totalCitizens' => 0
            ]);
        }

        // ✅ Fetch age group distribution
        $ageGroups = CitizenDetails::selectRaw("
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, 
            COUNT(*) as count
        ")
        ->whereIn('citizen_id', $citizenIds)
        ->groupBy('age_group')
        ->get();

        return response()->json([
            'serviceName' => $serviceName,
            'barangay' => $barangay,
            'ageGroups' => $ageGroups->pluck('count', 'age_group'),
            'totalCitizens' => $ageGroups->sum('count')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching the service data.',
            'error' => $e->getMessage(),
        ], 500);
    }
}








}
