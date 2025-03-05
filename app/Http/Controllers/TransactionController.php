<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\CitizenDetails;
use App\Models\Services;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;



class TransactionController extends Controller
{
    public function index()
    {
        return Transaction::all();
    }

public function store(Request $request)
{
    // Validate the request
    $validated = $request->validate([
        'citizen_id' => 'required|string',
        'service_id' => 'nullable|exists:services,id',
        'medicines' => ' nullable|array',
        'medicines.*.medicine_id' => 'nullable|exists:medicine,medicine_id',
        'medicines.*.quantity' => 'nullable|integer',
        'medicines.*.unit' => 'nullable|string',
    ]);

    DB::beginTransaction(); // Start transaction
    try {
        // Step 1: Validate stock availability for all medicines **BEFORE** making any changes
        $insufficientStock = [];
        foreach ($validated['medicines'] as $medicineData) {
            $medicine = Medicine::where('medicine_id', $medicineData['medicine_id'])->lockForUpdate()->first();

            if (!$medicine || $medicine->quantity < $medicineData['quantity']) {
                $insufficientStock[] = [
                    'medicine_id' => $medicineData['medicine_id'],
                    'name' => $medicine->name ?? 'Unknown',
                    'requested_quantity' => $medicineData['quantity'],
                    'available_stock' => $medicine->quantity ?? 0,
                ];
            }
        }

        // If any medicine is out of stock, return an error **before making changes**
        if (!empty($insufficientStock)) {
            DB::rollBack();
            return response()->json([
                'error' => 'Insufficient stock for some medicines.',
                'insufficient_stock' => $insufficientStock,
            ], 400);
        }

        // Step 2: Create transaction record
        $transaction = Transaction::create([
            'citizen_id' => $validated['citizen_id'],
            'service_id' => $validated['service_id'],
            'transaction_date' => now(),
        ]);

        $availedMedicines = [];

        // Step 3: Process each medicine (now that we know all stock is available)
        foreach ($validated['medicines'] as $medicineData) {
            $medicine = Medicine::where('medicine_id', $medicineData['medicine_id'])->lockForUpdate()->first();

            // Deduct stock
            $medicine->quantity -= $medicineData['quantity'];
            $medicine->save();

            // Insert into citizen_medicine table **WITHOUT checking for duplicates**
            DB::table('citizen_medicine')->insert([
                'citizen_id' => $validated['citizen_id'],
                'medicine_id' => $medicine->medicine_id,
                'transaction_id' => $transaction->id,
                'quantity' => $medicineData['quantity'],
                'unit' => $medicineData['unit'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $availedMedicines[] = [
                'medicine_id' => $medicine->medicine_id,
                'name' => $medicine->name,
                'availed_quantity' => $medicineData['quantity'],
                'remaining_stock' => $medicine->quantity,
                'unit' => $medicineData['unit'],
            ];
        }

        DB::commit(); // Commit transaction

        return response()->json([
            'message' => 'Transaction successfully created!',
            'transaction' => $transaction,
            'availed_medicines' => $availedMedicines,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack(); // Rollback transaction if an error occurs
        return response()->json(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
    }
}



public function show(Request $request, $citizen_id)
{
    // Fetch the citizen with transactions, services, and medicines
    $citizen = CitizenDetails::with(['transactions.service', 'transactions.medicines'])->find($citizen_id);

    if (!$citizen) {
        return response()->json(['message' => 'Citizen not found'], 404);
    }

    // Get month filter from request
    $month = $request->query('month');

    // Filter transactions by the selected month if provided
    $transactions = collect($citizen->transactions)->filter(function ($transaction) use ($month) {
        if (!$month) return true; // If no month is selected, return all transactions
        return date('m', strtotime($transaction->transaction_date)) == $month;
    })->map(function ($transaction) {
        return [
            'transaction_date' => $transaction->transaction_date,
            'service_availed' => optional($transaction->service)->name,
            'medicines_availed' => $transaction->medicines->map(function ($medicine) {
                return [
                    'name' => $medicine->name,
                    'quantity' => $medicine->pivot->quantity, // Get quantity from pivot table
                    'unit' => $medicine->pivot->unit, // Get unit from pivot table
                ];
            })->toArray(), // Convert to array
        ];
    })->values();

    return response()->json([
        'transactions' => $transactions,
    ]);
}



public function getAvailedCitizensByService($service_id)
{
    $service = Services::find($service_id);

    if (!$service) {
        return response()->json(['message' => 'Service not found'], 404);
    }

    // Fetch transactions with citizen relationship
    $transactions = Transaction::with('citizen')
        ->where('service_id', $service_id)
        ->orderBy('created_at', 'desc')
        ->get(); // Get all transactions first

    // Extract unique citizens from transactions
    $citizens = $transactions->map(function ($transaction) {
        $citizen = $transaction->citizen;

        if ($citizen) {
            return [
                'purok' => $citizen->purok,
                'lastname' => $citizen->lastname,
                'firstname' => $citizen->firstname,
                'middle_name' => $citizen->middle_name ?? "N/A",
                'suffix' => $citizen->suffix ?? "N/A",
                'created_at' => (new \DateTime($transaction->created_at))->format('m/d/Y'),
            ];
        }

        return null;
    })->filter();

    // Ensure unique citizens based on firstname & lastname
    $uniqueCitizens = $citizens->unique(function ($citizen) {
        return $citizen['firstname'] . ' ' . $citizen['lastname'];
    })->values();

    // Paginate unique citizens (10 per page)
    $perPage = 10;
    $currentPage = request()->get('page', 1); // Get current page from request
    $paginatedCitizens = $uniqueCitizens->forPage($currentPage, $perPage);

    if ($paginatedCitizens->isEmpty()) {
        $paginatedCitizens = [['message' => 'No citizen availed this service.']];
    }

    return response()->json([
        'service_name' => $service->name,
        'service_description' => $service->description ?? 'No description available.',
        'citizens' => $paginatedCitizens->values(), // Ensure keys reset
        'pagination' => [
            'current_page' => (int) $currentPage,
            'last_page' => ceil($uniqueCitizens->count() / $perPage),
            'per_page' => $perPage,
            'total' => $uniqueCitizens->count(),
            'next_page_url' => $currentPage < ceil($uniqueCitizens->count() / $perPage) ? url()->current() . '?page=' . ($currentPage + 1) : null,
            'prev_page_url' => $currentPage > 1 ? url()->current() . '?page=' . ($currentPage - 1) : null,
        ]
    ]);
}







}
