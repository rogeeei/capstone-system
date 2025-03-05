<?php

namespace App\Http\Controllers;

use App\Models\CitizenHistory;
use App\Models\Services;
use App\Models\CitizenDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CitizenHistoryController extends Controller
{
    // Get all citizen histories
    public function index()
    {
        $histories = CitizenHistory::with(['citizen'])->get();

        if ($histories->isEmpty()) {
            return response()->json(['message' => 'No histories found'], 404);
        }

        return response()->json($histories);
    }
public function show($citizenId)
{
    // Fetch the citizen's history, joining with the citizen_details table
    $history = DB::table('citizen_history')
        ->join('citizen_details', 'citizen_history.citizen_id', '=', 'citizen_details.citizen_id')
        ->select(
            'citizen_details.lastname',
            'citizen_details.firstname',
            'citizen_details.citizen_id',
            'citizen_details.gender',
            'citizen_details.date_of_birth',
            'citizen_details.purok',
            'citizen_history.created_at'
        )
        ->where('citizen_history.citizen_id', $citizenId) // Filter by citizen_id
        ->orderBy('citizen_history.created_at', 'desc')
        ->take(3) // Limit to the most recent 3 history records
        ->get();

    if ($history->isEmpty()) {
        return response()->json(['message' => 'No histories found'], 404);
    }

    // Attach services availed for each record in the history
    foreach ($history as $record) {
        $record->services_availed = CitizenDetails::find($record->citizen_id)
            ->services()
            ->pluck('name')
            ->toArray();
    }

    return response()->json($history);
}

public function getHistoryByMonth(Request $request)
{
    $validated = $request->validate([
        'citizen_id' => 'nullable|exists:citizen_details,citizen_id',
        'year' => 'nullable|integer|between:1900,' . (Carbon::now()->year),
        'month' => 'nullable|integer|between:1,12',
    ]);

    $citizenId = $validated['citizen_id'] ?? null;
    $year = $validated['year'] ?? null;
    $month = $validated['month'] ?? null;

    $query = DB::table('citizen_details')
        ->leftJoin('citizen_service', 'citizen_details.citizen_id', '=', 'citizen_service.citizen_id')
        ->leftJoin('services', 'citizen_service.service_id', '=', 'services.id')
        ->select(
            'citizen_details.citizen_id',
            'citizen_details.lastname',
            'citizen_details.firstname',
            'citizen_details.gender',
            'citizen_details.purok',
            'citizen_details.barangay',
            'citizen_details.municipality',
            'citizen_details.province',
            'citizen_details.date_of_birth',
            'citizen_details.created_at',
            'services.name as service_name',
            DB::raw('MONTH(citizen_details.created_at) as visit_month'),
            DB::raw('YEAR(citizen_details.created_at) as visit_year')
        );

    // Apply filters if provided
    if ($citizenId) {
        $query->where('citizen_details.citizen_id', $citizenId);
    }

    if ($year) {
        $query->whereYear('citizen_details.created_at', $year);
    }

    // If a month is provided, we filter by month
    if ($month) {
        $query->whereMonth('citizen_details.created_at', $month);
    }

    $history = $query->orderBy('citizen_details.created_at', 'desc')->get();

    if ($history->isEmpty()) {
        $message = $citizenId ? 'No history found for this citizen' : 'No histories found';
        return response()->json(['message' => $message], 404);
    }

    // Group the history by citizen first (grouping all citizens)
    $groupedHistory = $history->groupBy('citizen_id');

    // Format the history by citizen and group by month if month is provided
    $groupedHistory = $groupedHistory->map(function ($group) use ($month) {
        $group = $group->map(function ($item) use ($month) {
            $monthYear = Carbon::parse($item->created_at)->format('F Y');
            $item->visit_month_year = $monthYear;
            $item->gender = ucfirst(strtolower($item->gender));

            // If a month is provided, filter by month for the citizen
            if ($month) {
                if (Carbon::parse($item->created_at)->month != $month) {
                    return null;  // Skip if the month does not match
                }
            }

            return $item;
        });

        // Remove nulls from the collection (non-matching months)
        return $group->filter();
    });

    // Filter out empty groups (no records for the citizen in the selected month)
    $groupedHistory = $groupedHistory->filter(function ($group) {
        return $group->isNotEmpty();
    });

    return response()->json($groupedHistory);
}

public function getCitizenHistory(Request $request)
{
    // Get the authenticated user
    $user = auth()->user();

    // Ensure user has a barangay assigned
    if (!$user || !$user->brgy) {
        return response()->json([
            'success' => false,
            'message' => 'User does not have an assigned barangay.'
        ], 403);
    }

    // Get filter values from request
    $month = $request->input('month');
    $year = $request->input('year');

    // Base query: Fetch latest citizens from the same barangay
    $query = CitizenDetails::where('barangay', $user->brgy)
        ->orderBy('created_at', 'desc');

    // Apply month filter if selected
    if ($month) {
        $query->whereMonth('created_at', $month);
    }

    // Apply year filter if selected
    if ($year) {
        $query->whereYear('created_at', $year);
    }

    // Execute query and format the gender
    $citizens = $query->get()->map(function ($citizen) {
        $citizen->gender = ucfirst(strtolower($citizen->gender)); // Capitalize first letter
        return $citizen;
    });

    return response()->json([
        'success' => true,
        'message' => "Citizen history for barangay {$user->brgy} retrieved successfully.",
        'data' => $citizens
    ]);
}



}
