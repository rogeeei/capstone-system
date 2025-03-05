<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stakeholder;
use App\Models\Report;

class StakeholderContoller extends Controller
{
    public function store(Request $request)
{
    // Validate the incoming request
    $validated = $request->validate([
        'agency_name' => 'required|string|max:255',
        'purok' => 'required|string',
        'barangay' => 'required|string',
        'municipality' => 'required|string',
        'province' => 'required|string',
    ]);

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
