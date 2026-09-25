<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DemandController;
use App\Http\Controllers\DemandTaskController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');
Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [SessionController::class, 'create'])->name('login');
    Route::post('/entrar', [SessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/painel', fn () => view('dashboard'))->name('dashboard');
    Route::resource('demandas', DemandController::class)->only(['index', 'create', 'store', 'show'])->names('demands')->parameters(['demandas' => 'demand']);
    Route::patch('/demandas/{demand}/etapa', [DemandController::class, 'updateStatus'])->name('demands.status');
    Route::post('/demandas/{demand}/tarefas', [DemandTaskController::class, 'store'])->name('demand-tasks.store');
    Route::patch('/tarefas/{task}/status', [DemandTaskController::class, 'updateStatus'])->name('demand-tasks.status');
    Route::get('/equipe', [TeamMemberController::class, 'index'])->name('team.index');
    Route::post('/equipe', [TeamMemberController::class, 'store'])->name('team.store');
    Route::post('/sair', [SessionController::class, 'destroy'])->name('logout');
});
