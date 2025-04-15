<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stakeholder;
use App\Models\Report;
use App\Models\CitizenDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Services;
use App\Models\Transaction;
use App\Models\User;

class StakeholderContoller extends Controller
{
public function store(Request $request)
{
    // Validate the incoming request with password confirmation
    $validated = $request->validate([
        'agency_name' => 'required|string|max:255',
        'username' => 'required|string|max:255',
        'password' => 'required|string|min:8|confirmed', 
        'barangay' => 'required|string',
        'municipality' => 'required|string',
        'province' => 'required|string',
    ]);

    // Hash the password before saving
    $validated['password'] = bcrypt($validated['password']);

    // Create the stakeholder record
    $stakeholder = Stakeholder::create($validated);

    // Return the agency_name in the response along with the token
    return response()->json([
        'message' => 'Stakeholder created successfully',
        'data' => [
            'token' => $stakeholder->createToken('API Token')->plainTextToken,  // Assuming you are using Sanctum for token generation
            'agency_name' => $stakeholder->agency_name, // Add agency_name to the response
        ]
    ], 201);
}


public function getLoggedStakeholderProvinceReport()
{
    try {
        // ✅ Get the logged-in stakeholder
        $stakeholder = auth()->user();

        // ✅ Check if the stakeholder has an assigned province
        if (!$stakeholder || !$stakeholder->province) {
            return response()->json([
                "success" => false,
                "error" => "Stakeholder province not found."
            ], 400);
        }

        $province = $stakeholder->province;

        // ✅ Fetch distinct municipalities where users are approved
        $municipalities = User::where('province', $province)
            ->whereNotNull('municipality')
            ->where('approved', true) // ✅ Only include approved users
            ->distinct()
            ->pluck('municipality');

        // ✅ Total Population by Province
        $totalPopulation = CitizenDetails::where('province', $province)
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("CASE 
            WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
            WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
        END as gender, COUNT(*) as count")
        ->where('province', $province)
        ->whereNotNull('gender')
        ->groupBy('gender')
        ->pluck('count', 'gender');

        // ✅ Age Distribution
        $ageGroups = CitizenDetails::selectRaw("CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
            ELSE 'Elderly' 
        END as age_group, COUNT(*) as count")
        ->where('province', $province)
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->pluck('count', 'age_group');

        // ✅ BMI Classification
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

        // ✅ Medicine Availment
        $medicineData = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->selectRaw("medicine.name AS medicine_name, COUNT(DISTINCT citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment
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
            "municipalities" => $municipalities,
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

public function getStakeholderMunicipalityReport(Request $request, $municipality)
{
    try {
        // ✅ Get the province from the logged-in user
        $user = auth()->user();
        $province = $user->province;

        // ✅ Validate if municipality is provided
        if (!$municipality) {
            return response()->json([
                "success" => false,
                "error" => "Municipality parameter is required."
            ], 400);
        }

        // ✅ Total Population by Municipality
        $totalPopulation = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::selectRaw("CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(*) as count")
            ->where('province', $province)
            ->where('municipality', $municipality)
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // ✅ Age Distribution
        $ageGroups = CitizenDetails::selectRaw("CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, COUNT(*) as count")
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
            ->where('approved', true)
            ->whereNotNull('brgy')
            ->distinct()
            ->pluck('brgy');

        return response()->json([
            "success" => true,
            "message" => "Municipality report retrieved successfully.",
            "province" => $province,
            "municipality" => $municipality,
            "totalPopulation" => $totalPopulation,
            "genderDistribution" => $genderDistribution,
            "ageGroups" => $ageGroups,
            "bmiData" => $bmiData,
            "medicineData" => $medicineData,
            "serviceData" => $serviceData,
            "barangays" => $barangays
        ]);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Unable to fetch municipality report.",
            "details" => $e->getMessage()
        ], 500);
    }
}

public function getStakeholderBarangayReport(Request $request, $barangay)
{
    try {
        // ✅ Get the province from the logged-in user
        $user = auth()->user();
        $province = $user->province;

        // ✅ Validate if barangay is provided
        if (!$barangay) {
            return response()->json([
                "success" => false,
                "error" => "Barangay parameter is required."
            ], 400);
        }

        // ✅ Get the Municipality of the Barangay from Users table
        $municipality = User::where('brgy', $barangay)
            ->whereNotNull('municipality')
            ->value('municipality');

        if (!$municipality) {
            return response()->json([
                "success" => false,
                "error" => "Municipality not found for this Barangay."
            ], 404);
        }

        // ✅ Total Population by Barangay (Directly from citizen_details)
        $totalPopulation = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay) // ✅ No need to join users
            ->distinct('citizen_id')
            ->count();

        // ✅ Gender Distribution
        $genderDistribution = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay)
            ->whereNotNull('gender')
            ->selectRaw("CASE 
                WHEN LOWER(gender) IN ('male', 'm') THEN 'Male'
                WHEN LOWER(gender) IN ('female', 'f') THEN 'Female'
            END as gender, COUNT(*) as count")
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // ✅ Age Distribution
        $ageGroups = CitizenDetails::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay)
            ->whereNotNull('date_of_birth')
            ->selectRaw("CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 'Infant'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 5 THEN 'Toddler'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 19 THEN 'Teenager'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 20 AND 39 THEN 'Young Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 40 AND 59 THEN 'Middle-aged Adult'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 79 THEN 'Senior'
                ELSE 'Elderly' 
            END as age_group, COUNT(*) as count")
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

        // ✅ Medicine Availment by Barangay
        $medicineData = DB::table('citizen_medicine')
            ->join('medicine', 'citizen_medicine.medicine_id', '=', 'medicine.medicine_id')
            ->join('citizen_details', 'citizen_medicine.citizen_id', '=', 'citizen_details.citizen_id')
            ->where('citizen_details.province', $province)
            ->where('citizen_details.municipality', $municipality)
            ->where('citizen_details.barangay', $barangay)
            ->selectRaw("medicine.name AS medicine_name, COUNT(DISTINCT citizen_medicine.citizen_id) AS total_availed")
            ->groupBy('medicine.name')
            ->get();

        // ✅ Service Availment by Barangay
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
            "message" => "Barangay report retrieved successfully.",
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
            "error" => "Unable to fetch barangay report.",
            "details" => $e->getMessage()
        ], 500);
    }
}




    public function index(Request $request)
    {
        // Optionally filter unapproved stakeholders
        $stakeholders = Stakeholder::where('is_approved', false)->get();

        return response()->json($stakeholders);
    }
     public function getApprovedStakeholder(Request $request)
    {
        // Optionally filter unapproved stakeholders
        $stakeholders = Stakeholder::where('is_approved', true)->get();

        return response()->json($stakeholders);
    }
    public function approve($id)
    {
        $stakeholder = Stakeholder::find($id);

        if (!$stakeholder) {
            return response()->json(['message' => 'Stakeholder not found'], 404);
        }

        $stakeholder->is_approved = true;
        $stakeholder->save();

        return response()->json(['message' => 'Stakeholder approved successfully']);
    }

    public function decline($id)
    {
        $stakeholder = Stakeholder::find($id);

        if (!$stakeholder) {
            return response()->json(['message' => 'Stakeholder not found'], 404);
        }

        $stakeholder->delete();

        return response()->json(['message' => 'Stakeholder declined successfully']);
    }
    




}
