<?php

namespace App\Http\Controllers;

use App\Models\CitizenDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Services;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Medicine;
use Carbon\Carbon;





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

public function getDemographicSummaryByProvince(Request $request)
{
    $province = $request->query('province');

    if (!$province) {
        return response()->json(['error' => 'Province parameter is required'], 400);
    }
    try {
      
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
        ->where('province', $province) 
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->age_group => $item->count];
        });

        
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(DISTINCT citizen_id) as count
        ")
        ->where('province', $province)
        ->whereIn('gender', ['Male', 'male', 'Female', 'female', 'M', 'm', 'F', 'f'])
        ->groupBy('gender')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->gender => $item->count];
        });

        // **Total population count per province**
        $totalPopulation = CitizenDetails::where('province', $province)
            ->distinct('citizen_id')
            ->count();

        return response()->json([
            'province' => $province,
            'ageGroups' => $ageGroups,
            'genderDistribution' => $genderDistribution,
            'totalPopulation' => $totalPopulation
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to fetch demographic summary for province: ' . $e->getMessage());
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

public function getBarangayReport(Request $request)
{
    try {
        $user = auth()->user();

        // ✅ Get date range (optional)
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            try {
                $from = Carbon::parse($from)->startOfDay();
                $to = Carbon::parse($to)->endOfDay();
            } catch (\Exception $e) {
                return response()->json([
                    "success" => false,
                    "error" => "Invalid date format for 'from' or 'to'. Use YYYY-MM-DD."
                ], 400);
            }
        }

        // ✅ Determine location context
        if ($user->role === 'super admin') {
            $province = $request->query('province');
            $municipality = $request->query('municipality');
            $barangay = $request->query('barangay');

            if (!$province || !$municipality || !$barangay) {
                return response()->json([
                    "success" => false,
                    "error" => "Province, municipality, and barangay parameters are required for super admin."
                ], 400);
            }
        } else {
            $barangay = $user->brgy ?? null;
            $municipality = $user->municipality ?? null;
            $province = $user->province ?? null;

            if (!$barangay || !$municipality || !$province) {
                return response()->json([
                    "success" => false,
                    "error" => "User does not have assigned barangay, municipality, or province."
                ], 400);
            }
        }

        // ✅ Total Population
        $totalPopulation = CitizenDetails::where('barangay', $barangay)
            ->where('municipality', $municipality)
            ->where('province', $province)
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(DISTINCT citizen_id) as count
        ")
        ->where('barangay', $barangay)
        ->where('municipality', $municipality)
        ->where('province', $province)
        ->whereNotNull('gender')
        ->groupBy('gender')
        ->pluck('count', 'gender');

        // ✅ Age Groups
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
        ->where('barangay', $barangay)
        ->where('municipality', $municipality)
        ->where('province', $province)
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->pluck('count', 'age_group');

        // ✅ BMI
        $bmiData = CitizenDetails::where('barangay', $barangay)
            ->where('municipality', $municipality)
            ->where('province', $province)
            ->whereNotNull('height')
            ->whereNotNull('weight')
            ->get()
            ->groupBy(function ($citizen) {
                $height = floatval($citizen->height) / 100;
                $weight = floatval($citizen->weight);
                if ($height <= 0) return 'Unknown';

                $bmi = $weight / ($height * $height);
                if ($bmi < 18.5) return 'Underweight';
                if ($bmi >= 18.5 && $bmi <= 24.9) return 'Normal';
                if ($bmi >= 25 && $bmi <= 29.9) return 'Overweight';
                return 'Obese';
            })
            ->map(fn($group) => $group->count());

        // ✅ Medicine Availment
        $medicineQuery = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.barangay', $barangay)
            ->where('citizen_details.municipality', $municipality)
            ->where('citizen_details.province', $province);

        if ($from && $to) {
            $medicineQuery->whereBetween('citizen_medicine.created_at', [$from, $to]);
        }

        $medicineData = $medicineQuery
            ->selectRaw("medicine.name AS medicine_name, COUNT(citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment
        $serviceQuery = Transaction::join('services', 'transactions.service_id', '=', 'services.id')
            ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.barangay', $barangay)
            ->where('citizen_details.municipality', $municipality)
            ->where('citizen_details.province', $province);

        if ($from && $to) {
            $serviceQuery->whereBetween('transactions.created_at', [$from, $to]);
        }

        $serviceData = $serviceQuery
            ->selectRaw("services.name as service_name, COUNT(transactions.citizen_id) as total_availed")
            ->groupBy('services.name')
            ->get();

        // ✅ Log the queries for debugging
        \Log::info('Medicine Query:', [$medicineQuery->toSql()]);
        \Log::info('Medicine Query Parameters:', [$medicineQuery->getBindings()]);
        \Log::info('Service Query:', [$serviceQuery->toSql()]);
        \Log::info('Service Query Parameters:', [$serviceQuery->getBindings()]);

        // ✅ Final JSON response
        return response()->json([
            "success" => true,
            "message" => "Comprehensive report for barangay: $barangay",
            "province" => $province ?? null,
            "municipality" => $municipality ?? null,
            "barangay" => $barangay,
            "totalPopulation" => $totalPopulation,
            "genderDistribution" => $genderDistribution,
            "ageGroups" => $ageGroups,
            "bmiData" => $bmiData,
            "medicineData" => $medicineData,
            "serviceData" => $serviceData,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch report data",
            "details" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ], 500);
    }
}







//Province Report JS
public function getProvinceReport($province)
{
    try {
        // ✅ Check if province is provided
        if (!$province) {
            return response()->json([
                "success" => false,
                "error" => "Province parameter is required."
            ], 400);
        }

        // ✅ Total Population by Province
        $totalPopulation = CitizenDetails::where('province', $province)->distinct('citizen_id')->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(*) as count
        ")
        ->where('province', $province)
        ->whereNotNull('gender')
        ->groupBy('gender')
        ->pluck('count', 'gender');

        // ✅ Age Distribution
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
            END as age_group, COUNT(*) as count
        ")
        ->where('province', $province)
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->pluck('count', 'age_group');

        // ✅ BMI Classification (Using citizen_details)
        $bmiData = CitizenDetails::where('province', $province)
            ->whereNotNull('height')
            ->whereNotNull('weight')
            ->get()
            ->groupBy(function ($citizen) {
                $height = floatval($citizen->height) / 100;
                $weight = floatval($citizen->weight);
                if ($height <= 0) return 'Unknown';

                $bmi = $weight / ($height * $height);
                if ($bmi < 18.5) return 'Underweight';
                if ($bmi >= 18.5 && $bmi <= 24.9) return 'Normal';
                if ($bmi >= 25 && $bmi <= 29.9) return 'Overweight';
                return 'Obese';
            })
            ->map(fn($group) => $group->count());

        // ✅ Medicine Availment by Province
        $medicineData = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->selectRaw("medicine.name AS medicine_name, COUNT(DISTINCT citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment by Province
        $serviceData = Transaction::join('services', 'transactions.service_id', '=', 'services.id')
            ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->selectRaw("services.name as service_name, COUNT(DISTINCT transactions.citizen_id) as total_availed")
            ->groupBy('services.name')
            ->get();

        return response()->json([
            "success" => true,
            "message" => "Comprehensive report for province: $province",
            "province" => $province,
            "totalPopulation" => $totalPopulation,
            "genderDistribution" => $genderDistribution,
            "ageGroups" => $ageGroups,
            "bmiData" => $bmiData,
            "medicineData" => $medicineData,
            "serviceData" => $serviceData
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch report data",
            "details" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ], 500);
    }
}

 
//Municipal Report JS
public function getMunicipalityReport($province, $municipality)
{
    try {
        // ✅ Check if both province and municipality are provided
        if (!$province || !$municipality) {
            return response()->json([
                "success" => false,
                "error" => "Province and municipality parameters are required."
            ], 400);
        }

        // ✅ Total Population by Municipality
        $totalPopulation = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(*) as count
        ")
        ->where('province', $province)
        ->where('municipality', $municipality)
        ->whereNotNull('gender')
        ->groupBy('gender')
        ->pluck('count', 'gender');

        // ✅ Age Distribution
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
            END as age_group, COUNT(*) as count
        ")
        ->where('province', $province)
        ->where('municipality', $municipality)
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->pluck('count', 'age_group');

        // ✅ BMI Classification
        $bmiData = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->whereNotNull('height')
            ->whereNotNull('weight')
            ->get()
            ->groupBy(function ($citizen) {
                $height = floatval($citizen->height) / 100;
                $weight = floatval($citizen->weight);
                if ($height <= 0) return 'Unknown';

                $bmi = $weight / ($height * $height);
                if ($bmi < 18.5) return 'Underweight';
                if ($bmi >= 18.5 && $bmi <= 24.9) return 'Normal';
                if ($bmi >= 25 && $bmi <= 29.9) return 'Overweight';
                return 'Obese';
            })
            ->map(fn($group) => $group->count());

        // ✅ Medicine Availment by Municipality
        $medicineData = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->where('citizen_details.municipality', $municipality)
            ->selectRaw("medicine.name AS medicine_name, COUNT(DISTINCT citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment by Municipality
        $serviceData = Transaction::join('services', 'transactions.service_id', '=', 'services.id')
            ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->where('citizen_details.municipality', $municipality)
            ->selectRaw("services.name as service_name, COUNT(DISTINCT transactions.citizen_id) as total_availed")
            ->groupBy('services.name')
            ->get();

        // ✅ Fetch Barangays under the Municipality (Only from Approved Users)
        $barangays = User::where('municipality', $municipality)
            ->where('approved', true) // ✅ Only approved users
            ->whereNotNull('brgy')
            ->distinct()
            ->pluck('brgy');

        return response()->json([
            "success" => true,
            "message" => "Comprehensive report for municipality: $municipality in province: $province",
            "province" => $province,
            "municipality" => $municipality,
            "totalPopulation" => $totalPopulation,
            "genderDistribution" => $genderDistribution,
            "ageGroups" => $ageGroups,
            "bmiData" => $bmiData,
            "medicineData" => $medicineData,
            "serviceData" => $serviceData,
            "barangays" => $barangays // ✅ Only for approved users
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch report data",
            "details" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ], 500);
    }
}

public function getBarangayReportWithParams($province, $municipality, $barangay)
{
    try {
        if (!$province || !$municipality || !$barangay) {
            return response()->json([
                "success" => false,
                "error" => "Province, municipality, and barangay parameters are required."
            ], 400);
        }

        // ✅ Total Population
        $totalPopulation = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay)
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("
            CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(*) as count
        ")
        ->where('province', $province)
        ->where('municipality', $municipality)
        ->where('barangay', $barangay)
        ->whereNotNull('gender')
        ->groupBy('gender')
        ->pluck('count', 'gender');

        // ✅ Age Distribution
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
            END as age_group, COUNT(*) as count
        ")
        ->where('province', $province)
        ->where('municipality', $municipality)
        ->where('barangay', $barangay)
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->pluck('count', 'age_group');

        // ✅ BMI Classification
        $bmiData = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay)
            ->whereNotNull('height')
            ->whereNotNull('weight')
            ->get()
            ->groupBy(function ($citizen) {
                $height = floatval($citizen->height) / 100;
                $weight = floatval($citizen->weight);
                if ($height <= 0) return 'Unknown';

                $bmi = $weight / ($height * $height);
                if ($bmi < 18.5) return 'Underweight';
                if ($bmi >= 18.5 && $bmi <= 24.9) return 'Normal';
                if ($bmi >= 25 && $bmi <= 29.9) return 'Overweight';
                return 'Obese';
            })
            ->map(fn($group) => $group->count());

        // ✅ Medicine Availment
        $medicineData = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->where('citizen_details.municipality', $municipality)
            ->where('citizen_details.barangay', $barangay)
            ->selectRaw("medicine.name AS medicine_name, COUNT(DISTINCT citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment
        $serviceData = Transaction::join('services', 'transactions.service_id', '=', 'services.id')
            ->join('citizen_details', 'transactions.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->where('citizen_details.municipality', $municipality)
            ->where('citizen_details.barangay', $barangay)
            ->selectRaw("services.name as service_name, COUNT(DISTINCT transactions.citizen_id) as total_availed")
            ->groupBy('services.name')
            ->get();

        return response()->json([
            "success" => true,
            "message" => "Comprehensive report for barangay: $barangay",
            "province" => $province,
            "municipality" => $municipality,
            "barangay" => $barangay,
            "totalPopulation" => $totalPopulation,
            "genderDistribution" => $genderDistribution,
            "ageGroups" => $ageGroups,
            "bmiData" => $bmiData,
            "medicineData" => $medicineData,
            "serviceData" => $serviceData
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch report data",
            "details" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ], 500);
    }
}


}
