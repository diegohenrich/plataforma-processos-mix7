<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SessionController;

Route::redirect('/', '/painel');
Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [SessionController::class, 'create'])->name('login');
    Route::post('/entrar', [SessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/painel', fn () => view('dashboard'))->name('dashboard');
    Route::post('/sair', [SessionController::class, 'destroy'])->name('logout');
});
