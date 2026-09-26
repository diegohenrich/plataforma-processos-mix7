<?php

use App\Http\Controllers\Api\V1\DemandController;
use App\Http\Controllers\DemandController as WebDemandController;
use App\Http\Controllers\DemandDeliveryEvidenceController;
use App\Http\Controllers\DemandReviewController;
use App\Http\Controllers\DemandTaskController;
use App\Http\Controllers\PerformanceReviewController;
use App\Http\Controllers\TeamActivityController;
use App\Http\Controllers\TeamInvitationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/public/reviews/{token}', [DemandReviewController::class, 'showApi'])->middleware('throttle:30,1');
    Route::post('/public/reviews/{token}/responses', [DemandReviewController::class, 'respond'])->middleware('throttle:10,1');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/me', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'organization_id' => $user->organization_id,
            ],
        ]);
    });
    Route::get('/demands', [DemandController::class, 'index']);
    Route::get('/demands/{demand}', [DemandController::class, 'show']);
    Route::get('/team/activity', [TeamActivityController::class, 'index']);
    Route::post('/demands', [WebDemandController::class, 'store']);
    Route::patch('/demands/{demand}/status', [WebDemandController::class, 'updateStatus']);
    Route::post('/demands/{demand}/tasks', [DemandTaskController::class, 'store']);
    Route::post('/demands/{demand}/delivery-evidences', [DemandDeliveryEvidenceController::class, 'store']);
    Route::post('/demands/{demand}/review-links', [DemandReviewController::class, 'store']);
    Route::delete('/demands/{demand}/review-links/{reviewLink}', [DemandReviewController::class, 'revoke']);
    Route::post('/team/invitations', [TeamInvitationController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('/team/invitations/{invitation}', [TeamInvitationController::class, 'revoke']);
    Route::post('/team/performance-reviews', [PerformanceReviewController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/team/performance-reviews/{review}/responses', [PerformanceReviewController::class, 'respond'])->middleware('throttle:10,1');
    Route::patch('/tasks/{task}/status', [DemandTaskController::class, 'updateStatus']);
    Route::post('/tasks/{task}/timer/start', [DemandTaskController::class, 'startTimer']);
    Route::post('/tasks/{task}/timer/pause', [DemandTaskController::class, 'pauseTimer']);
    Route::patch('/tasks/{task}/schedule', [DemandTaskController::class, 'updateSchedule']);
    Route::patch('/tasks/{task}/assignee', [DemandTaskController::class, 'updateAssignee']);
    Route::post('/tasks/timer/recover', [DemandTaskController::class, 'recoverTimer']);
});
