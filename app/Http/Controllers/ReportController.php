<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Stakeholder;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'stakeholder_id' => 'required|exists:stakeholders,id',
            'user_id' => 'nullable|exists:users,user_id',
            'report_title' => 'required|string|max:255',
            'report_description' => 'nullable|string',
        ]);

        $report = Report::create($validated);

        return response()->json([
            'message' => 'Report created successfully.',
            'data' => $report,
        ], 201);
    }

    public function getLatestReport()
{
    // Retrieve the latest report based on the creation date
    $latestReport = Report::whereNotNull('report_title') // Ensure report has a title
        ->whereNotNull('report_description') // Ensure report has a description
        ->orderBy('created_at', 'desc') // Order by creation date to get the latest report
        ->first(); // Only get the latest one

    if (!$latestReport) {
        return response()->json(['message' => 'No report found'], 404);
    }

    return response()->json($latestReport);
}


}
