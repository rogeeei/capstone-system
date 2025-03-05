<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\CitizenDetailsController;
use App\Http\Controllers\CitizenHistoryController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SummaryReportController;
use App\Http\Controllers\CitizenServiceController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\StakeholderContoller;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Public API
Route::post('/login', [AuthController::class, 'login'])->name('user.login');
Route::post('/user', [UserController::class, 'store'])->name('user.store');
Route::post('/stakeholders',  [StakeholderContoller::class, 'store']);
Route::post('/stakeholders/login',  [AuthController::class, 'stakeholderLogin']);




//Private API
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);

    // Super Admin API
     Route::controller(SuperAdminController::class)->group(function () {
        
        Route::delete('/user',          'destroy');
        Route::get('/superadmin',                  'getAdmin');
        Route::get('/bhw',                  'getUsers');
        Route::get('/getalluser',                  'index');
    });
  

    //Admin APIs
    Route::post('/admin/users', [AdminController::class, 'addUser']);
    Route::patch('/user/{user}/approve', [AdminController::class, 'approveUser']);
    Route::patch('/user/decline/{id}', [AdminController::class, 'declineUser']);

    // Stakeholder API
    Route::controller(StakeholderContoller::class)->group(function () {
    Route::get('/stakeholder',                  'index');
    Route::get('/approved-stakeholder',                  'getApprovedStakeholder');
    Route::post('/stakeholders/{id}/approve', 'approve');
    Route::post('/stakeholders/{id}/decline', 'decline');
    Route::post('/stakeholders/{id}/request', 'submitRequest');
    Route::get('/request',                  'displayRequest');
     Route::post('/request/{id}/approve', 'approveRequest');
    Route::post('/request/{id}/decline', 'declineRequest');
     Route::get('/stakeholders/{id}/report', 'displayReportsByStakeholder');


});

Route::post('/reports', [ReportController::class, 'store']);
Route::get('report/latest', [ReportController::class, 'getLatestReport']);


Route::post('/transactions', [TransactionController::class, 'store']);
Route::get('/show/transaction/{citizen_id}', [TransactionController::class, 'show']);
Route::get('/show/availed-citizens/{service_id}', [TransactionController::class, 'getAvailedCitizensByService']);


    Route::controller(CitizenDetailsController::class)->group(function () {
        Route::get('/citizen',                   'index');
        Route::post('/citizen',                  'store');
        Route::delete('/citizen/{id}',           'destroy');
        Route::put('/citizen/{id}',              'update');
        Route::get('/citizen/{id}',              'show');
        Route::get('/services-summary',          'getServicesSummary');
        Route::get('/citizen-overview',          'getCitizenVisitHistory');
        Route::get('/service-view',                  'fetchServicesView');
        Route::get('/service-index',                  'getCitizens');
        Route::get('/transaction/{id}',          'getTransaction');
        Route::get('/barangays', 'getDistinctUserBarangays');
        Route::get('/citizens/barangay', 'getCitizensByBarangay');

    });

   Route::controller(CitizenHistoryController::class)->group(function () {
    Route::get('/citizen-history-index', 'index'); 
    Route::get('/citizen-history','getCitizenHistory');
    Route::get('/citizen-history/{citizenId}', 'show'); 
    Route::get('/monthly-history',                  'getHistoryByMonth');
});


Route::controller(MedicineController::class)->group(function () {
    Route::post('/medicine', 'store');
    Route::get('/medicine/{id}', 'show');
    Route::get('/medicine/all', 'getAllMedcine');
    Route::put('/medicine/{id}', 'update');
    Route::get('/medicine', 'index');
    Route::get('/medicines/by-barangay', [MedicineController::class, 'getMedicinesByBarangay']);

    Route::get('/medicines/available', 'getAvailableMedicines');
    Route::post('/citizen/{citizen}/avail-medicine', 'availMedicine');
    

    // Ensure this route is inside the group
    Route::patch('/medicine/{medicine_id}/update-stock', 'updateMedicineStock');
});


    Route::controller(EquipmentController::class)->group(function () {
        Route::get('/equipment/barangay', 'getEquipmentByBarangay');
        Route::get('/equipment',                   'index');
        Route::post('/equipment',                  'store');
        Route::get('/equipment/{id}',               'show');
        Route::put('/equipment/{id}',             'update');
        Route::delete('/equipment/{id}',          'destroy');
        Route::patch('/equipment/{id}/update-stock',  'updateEquipmentStock');


    });
    

    Route::controller(UserController::class)->group(function () {
        Route::get('/get-bhw',                     'getBhw');
        Route::get('/user/{id}',                'show');
        Route::get('/user-details',              'getUserDetails');
        Route::put('/user/{id}',                'update');
        Route::delete('/user/{id}',             'destroy');
    });

    Route::controller(CitizenServiceController::class)->group(function () {

        Route::delete('/diagnostics/{id}',          'destroy');
        Route::get('/service-availed/{id}',        'show');
    });

        Route::controller(ServicesController::class)->group(function () {
        Route::post('/services',                 'store');
        Route::get('/services',                 'index');
        Route::delete('/services/{id}',          'destroy');
        Route::get('/summary',                  'showServicesSummary');
        Route::get('/services-by-barangay',                 'getServicesByBarangay');
    }); 
 
    //User Specific APIs
    Route::get('/citizens/availed/{serviceId}', [CitizenServiceController::class, 'getCitizensByService']);
    Route::get('/demo-summary', [SummaryReportController::class, 'getDemographicSummary']);
    Route::get('/demo/brgy/{barangay}', [SummaryReportController::class, 'getDemographicSummaryByBarangay']);
    Route::get('/services/{serviceId}/age-distribution', [SummaryReportController::class, 'getServiceWithAgeDistributionByBarangay']);
    Route::get('/services', [ServicesController::class, 'index']);
    Route::get('/services/all', [ServicesController::class, 'getServices']);
    Route::get('/services/brgy', [ServicesController::class, 'getCitizenServicesByBarangay']);
    Route::get('/service/{serviceName}/age-distribution', [SummaryReportController::class, 'getServiceWithAgeDistribution']);
    Route::get('/services/{serviceName}/age', [SummaryReportController::class, 'getAdminServiceWithAgeDistributionByBarangay']);
});
