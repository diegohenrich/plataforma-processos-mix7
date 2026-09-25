<?php

use App\Http\Controllers\Api\V1\DemandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
});
