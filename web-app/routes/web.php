<?php

use App\Http\Controllers\AiPlanningController;
use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DemandController;
use App\Http\Controllers\DemandReviewController;
use App\Http\Controllers\DemandTaskController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');
Route::get('/revisao/{token}', [DemandReviewController::class, 'show'])->middleware('throttle:30,1')->name('client-reviews.show');
Route::get('/revisao/{token}/material', [DemandReviewController::class, 'material'])->middleware('throttle:60,1')->name('client-reviews.material');
Route::post('/revisao/{token}/respostas', [DemandReviewController::class, 'respond'])->middleware('throttle:10,1')->name('client-reviews.respond');
Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [SessionController::class, 'create'])->name('login');
    Route::post('/entrar', [SessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/painel', fn () => view('dashboard'))->name('dashboard');
    Route::resource('demandas', DemandController::class)->only(['index', 'create', 'store', 'show'])->names('demands')->parameters(['demandas' => 'demand']);
    Route::patch('/demandas/{demand}/etapa', [DemandController::class, 'updateStatus'])->name('demands.status');
    Route::post('/demandas/{demand}/planejamento-ia', [AiPlanningController::class, 'propose'])->middleware('throttle:3,1')->name('ai-planning.propose');
    Route::post('/demandas/{demand}/planejamento-ia/{run}/aprovar', [AiPlanningController::class, 'approve'])->name('ai-planning.approve');
    Route::delete('/demandas/{demand}/planejamento-ia/{run}', [AiPlanningController::class, 'discard'])->name('ai-planning.discard');
    Route::post('/demandas/{demand}/assistente', [AiAgentController::class, 'ask'])->middleware('throttle:3,1')->name('ai-agent.ask');
    Route::get('/demandas/{demand}/assistente/{run}/status', [AiAgentController::class, 'status'])->name('ai-agent.status');
    Route::post('/demandas/{demand}/links-revisao', [DemandReviewController::class, 'store'])->name('demand-reviews.store');
    Route::delete('/demandas/{demand}/links-revisao/{reviewLink}', [DemandReviewController::class, 'revoke'])->name('demand-reviews.revoke');
    Route::get('/demandas/{demand}/links-revisao/{reviewLink}/material', [DemandReviewController::class, 'teamMaterial'])->name('demand-reviews.team-material');
    Route::post('/demandas/{demand}/tarefas', [DemandTaskController::class, 'store'])->name('demand-tasks.store');
    Route::post('/tarefas/{task}/cronometro/iniciar', [DemandTaskController::class, 'startTimer'])->name('demand-tasks.timer.start');
    Route::post('/tarefas/{task}/cronometro/pausar', [DemandTaskController::class, 'pauseTimer'])->name('demand-tasks.timer.pause');
    Route::patch('/tarefas/{task}/status', [DemandTaskController::class, 'updateStatus'])->name('demand-tasks.status');
    Route::get('/conhecimento', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/conhecimento', [KnowledgeController::class, 'store'])->name('knowledge.store');
    Route::get('/conhecimento/arquivados', [KnowledgeController::class, 'archived'])->name('knowledge.archived');
    Route::put('/conhecimento/{item}', [KnowledgeController::class, 'update'])->name('knowledge.update');
    Route::delete('/conhecimento/{item}', [KnowledgeController::class, 'archive'])->name('knowledge.archive');
    Route::post('/conhecimento/{item}/restaurar', [KnowledgeController::class, 'restore'])->name('knowledge.restore');
    Route::post('/conhecimento/{item}/atribuir', [KnowledgeController::class, 'assign'])->name('knowledge.assign');
    Route::patch('/onboarding/{assignment}/etapas/{step}', [KnowledgeController::class, 'toggleStep'])->name('knowledge.assignment-step');
    Route::get('/equipe', [TeamMemberController::class, 'index'])->name('team.index');
    Route::post('/equipe', [TeamMemberController::class, 'store'])->name('team.store');
    Route::post('/sair', [SessionController::class, 'destroy'])->name('logout');
});
