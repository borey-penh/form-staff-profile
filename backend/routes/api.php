<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

// Invitation magic-link (public — token is the credential)
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{token}/claim', [InvitationController::class, 'claim']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Personnel profile (5-step wizard)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile/personal', [ProfileController::class, 'updatePersonal']);
    Route::put('/profile/qualifications', [ProfileController::class, 'saveQualifications']);
    Route::put('/profile/family', [ProfileController::class, 'saveFamily']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::post('/profile/documents', [ProfileController::class, 'uploadDocument']);
    Route::delete('/profile/documents/{id}', [ProfileController::class, 'deleteDocument']);
    Route::post('/profile/declaration', [ProfileController::class, 'declare']);
    Route::post('/profile/change-requests', [ProfileController::class, 'submitChangeRequest']);
    Route::delete('/profile/change-requests/{id}', [ProfileController::class, 'cancelChangeRequest']);

    // Compliances
    Route::get('/compliances', [ComplianceController::class, 'index']);
    Route::post('/compliances/sign', [ComplianceController::class, 'sign']);

    // Trainings
    Route::get('/trainings', [TrainingController::class, 'index']);
    Route::get('/trainings/{id}', [TrainingController::class, 'show']);
    Route::post('/trainings/{id}/progress', [TrainingController::class, 'progress']);
    Route::post('/trainings/{id}/quiz', [TrainingController::class, 'submitQuiz']);

    // Contracts
    Route::get('/contracts', [ContractController::class, 'index']);

    // Unified requests (leave, overtime, travel, fuel, purchase, voucher, timesheet)
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/requests/{id}', [RequestController::class, 'show']);
    Route::post('/requests/{id}/update', [RequestController::class, 'update']);
    Route::post('/requests/{id}/act', [RequestController::class, 'act']);

    // Misc reference data
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::get('/leave-balances', fn (\Illuminate\Http\Request $request) => response()->json([
        'data' => $request->user()->leaveBalances()->get()->map(fn ($b) => [
            'type' => $b->type,
            'entitled' => $b->entitled,
            'used' => $b->used,
            'remaining' => max(0, $b->entitled - $b->used),
        ]),
    ]));
    Route::get('/vehicles', fn () => response()->json([
        'data' => \App\Models\Vehicle::orderBy('name')->get(['id', 'name']),
    ]));

    // Access Management (roles, users, change requests)
    Route::get('/access/roles', [AccessController::class, 'roles'])->middleware('can:users.manage');
    Route::get('/access/roles/{role}', [AccessController::class, 'roleShow'])->middleware('can:users.manage');
    Route::post('/access/roles', [AccessController::class, 'roleStore'])->middleware('can:roles.manage');
    Route::put('/access/roles/{role}', [AccessController::class, 'roleUpdate'])->middleware('can:roles.manage');
    Route::delete('/access/roles/{role}', [AccessController::class, 'roleDestroy'])->middleware('can:roles.manage');
    Route::get('/access/users', [AccessController::class, 'users'])->middleware('can:users.manage');
    Route::get('/access/users/{user}', [AccessController::class, 'userShow'])->middleware('can:users.manage');
    Route::put('/access/users/{user}', [AccessController::class, 'userUpdate'])->middleware('can:users.manage');
    Route::get('/access/change-requests', [AccessController::class, 'changeRequestIndex'])->middleware('can:profile.change-requests.review');
    Route::post('/access/change-requests/{id}/review', [AccessController::class, 'changeRequestReview'])->middleware('can:profile.change-requests.review');
    Route::get('/access/invitations', [InvitationController::class, 'index'])->middleware('can:users.manage');
    Route::post('/access/invitations', [InvitationController::class, 'store'])->middleware('can:users.manage');
    Route::post('/access/invitations/{id}/resend', [InvitationController::class, 'resend'])->middleware('can:users.manage');
    Route::delete('/access/invitations/{id}', [InvitationController::class, 'revoke'])->middleware('can:users.manage');

    // Admin / HR portal
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/admin/staff', [AdminController::class, 'staffIndex']);
        Route::post('/admin/staff', [AdminController::class, 'staffStore']);
        Route::get('/admin/staff/{id}', [AdminController::class, 'staffShow']);
        Route::get('/admin/departments', [AdminController::class, 'departments']);
        Route::get('/admin/compliances', [AdminController::class, 'complianceIndex']);
        Route::post('/admin/compliances', [AdminController::class, 'complianceStore']);
        Route::put('/admin/compliances/{id}', [AdminController::class, 'complianceUpdate']);
        Route::delete('/admin/compliances/{id}', [AdminController::class, 'complianceDestroy']);
        Route::get('/admin/trainings', [AdminController::class, 'trainingIndex']);
        Route::post('/admin/trainings', [AdminController::class, 'trainingStore']);
        Route::post('/admin/trainings/assign', [AdminController::class, 'trainingAssign']);
        Route::post('/admin/documents/{id}/verify', [AdminController::class, 'documentVerify']);
        Route::post('/admin/contracts', [AdminController::class, 'contractStore']);
        Route::get('/admin/vouchers', [AdminController::class, 'voucherIndex']);
        Route::post('/admin/vouchers/{id}/pay', [AdminController::class, 'voucherPay']);
        Route::post('/admin/holidays', [HolidayController::class, 'store']);
        Route::delete('/admin/holidays/{id}', [HolidayController::class, 'destroy']);
        Route::get('/admin/reports', [AdminController::class, 'reports']);
    });
});
